<?php

use App\Enums\StatutCompte;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function prestataireAffichage(): Prestataire
{
    return Prestataire::factory()->for(User::factory()->prestataire()->statut(StatutCompte::Actif), 'user')->create();
}

it('refuse sans jeton', function () {
    $this->getJson('/api/v1/prestataire/photos/'.fake()->uuid())->assertUnauthorized();
});

it('refuse un client et un administrateur', function () {
    foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/prestataire/photos/'.fake()->uuid())->assertForbidden();
    }
});

it('renvoie l\'image du propriétaire avec les bons en-têtes', function () {
    $prestataire = prestataireAffichage();
    Sanctum::actingAs($prestataire->user);
    $fichier = fichierJustificatif('jpg', 'atelier.jpg');
    $octets = file_get_contents($fichier->getPathname());
    $id = $this->postJson('/api/v1/prestataire/photos', ['photo' => $fichier])->assertCreated()->json('id');

    $reponse = $this->get("/api/v1/prestataire/photos/$id");

    $reponse->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($reponse->headers->get('Cache-Control'))->toContain('private')
        ->and($reponse->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($reponse->streamedContent())->toBe($octets);
});

it('répond 404 pour la photo d\'un autre prestataire', function () {
    $autre = prestataireAffichage();
    Sanctum::actingAs($autre->user);
    $idAutre = $this->postJson('/api/v1/prestataire/photos', ['photo' => fichierJustificatif('png')])->json('id');

    Sanctum::actingAs(prestataireAffichage()->user);

    $this->getJson("/api/v1/prestataire/photos/$idAutre")->assertNotFound()->assertJsonPath('message', 'Photo introuvable.');
});

it('répond 404 pour une photo inexistante ou supprimée', function () {
    Sanctum::actingAs(prestataireAffichage()->user);
    $id = $this->postJson('/api/v1/prestataire/photos', ['photo' => fichierJustificatif('png')])->json('id');
    $this->deleteJson("/api/v1/prestataire/photos/$id")->assertNoContent();

    $this->getJson('/api/v1/prestataire/photos/'.fake()->uuid())->assertNotFound();
    $this->getJson("/api/v1/prestataire/photos/$id")->assertNotFound();
});

it('inclut l\'url de chaque photo dans la liste, et cette url fonctionne', function () {
    Sanctum::actingAs(prestataireAffichage()->user);
    $a = $this->postJson('/api/v1/prestataire/photos', ['photo' => fichierJustificatif('png')])->json('id');
    $b = $this->postJson('/api/v1/prestataire/photos', ['photo' => fichierJustificatif('jpg')])->json('id');

    $liste = $this->getJson('/api/v1/prestataire/photos')->assertOk();

    expect($liste->json('data'))->toBe([
        ['id' => $a, 'url' => "/api/v1/prestataire/photos/$a"],
        ['id' => $b, 'url' => "/api/v1/prestataire/photos/$b"],
    ]);

    $this->get($liste->json('data.0.url'))->assertOk()->assertHeader('Content-Type', 'image/png');
});
