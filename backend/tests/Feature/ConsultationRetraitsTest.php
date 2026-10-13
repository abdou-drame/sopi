<?php

use App\Http\Resources\RetraitResource;
use App\Models\Prestataire;
use App\Models\Retrait;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Prestataire actif connecté à l'API, avec un solde. */
function prestataireConsultant(string|int|float $solde = 0): Prestataire
{
    $prestataire = Prestataire::factory()->avoirSolde($solde)->for(User::factory()->prestataire(), 'user')->create();
    test()->actingAs($prestataire->user, 'sanctum');

    return $prestataire;
}

describe('accès aux routes de solde et de retraits', function () {
    it('renvoie 401 sans jeton, puis 403 pour un client et un administrateur', function () {
        $retrait = Retrait::factory()->create();
        $urls = ['/api/v1/prestataire/solde', '/api/v1/prestataire/retraits', "/api/v1/prestataire/retraits/{$retrait->id}"];

        foreach ($urls as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum');
            foreach ($urls as $url) {
                $this->getJson($url)->assertForbidden();
            }
        }
    });
});

describe('solde', function () {
    it('renvoie le solde et les bornes de la configuration', function () {
        prestataireConsultant(12500);

        $this->getJson('/api/v1/prestataire/solde')->assertOk()->assertExactJson([
            'solde' => 12500.0,
            'montantMinimumRetrait' => 1000,
            'montantMaximumRetrait' => 500000,
        ]);
    });

    it('vaut 0 pour un nouveau prestataire et suit la configuration', function () {
        config(['sopi.retraits.montant_minimum' => 2000, 'sopi.retraits.montant_maximum' => 300000]);
        prestataireConsultant();

        $this->getJson('/api/v1/prestataire/solde')->assertOk()->assertExactJson([
            'solde' => 0.0,
            'montantMinimumRetrait' => 2000,
            'montantMaximumRetrait' => 300000,
        ]);
    });

    it('ne montre que le solde du prestataire connecté', function () {
        Prestataire::factory()->avoirSolde(99999)->create();
        prestataireConsultant(700);

        $this->getJson('/api/v1/prestataire/solde')->assertOk()->assertJsonPath('solde', 700);
    });
});

describe('historique', function () {
    it('liste du plus récent au plus ancien, avec la pagination habituelle', function () {
        $prestataire = prestataireConsultant();
        $ancien = Retrait::factory()->reussi()->create(['prestataire_id' => $prestataire->id, 'created_at' => now()->subDays(2)]);
        $recent = Retrait::factory()->create(['prestataire_id' => $prestataire->id, 'created_at' => now()->subHour()]);
        $milieu = Retrait::factory()->echoue()->create(['prestataire_id' => $prestataire->id, 'created_at' => now()->subDay()]);

        $reponse = $this->getJson('/api/v1/prestataire/retraits')->assertOk();

        expect(collect($reponse->json('data'))->pluck('id')->all())->toBe([$recent->id, $milieu->id, $ancien->id]);
        expect($reponse->json('meta'))->toMatchArray(['current_page' => 1, 'per_page' => 20, 'total' => 3]);
    });

    it('pagine par 20', function () {
        $prestataire = prestataireConsultant();
        Retrait::factory()->count(25)->create(['prestataire_id' => $prestataire->id]);

        $this->getJson('/api/v1/prestataire/retraits')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/prestataire/retraits?page=2')->assertOk()->assertJsonCount(5, 'data');
    });

    it('ne montre que les retraits du prestataire connecté', function () {
        Retrait::factory()->count(2)->create();
        $prestataire = prestataireConsultant();
        $mien = Retrait::factory()->create(['prestataire_id' => $prestataire->id]);

        $reponse = $this->getJson('/api/v1/prestataire/retraits')->assertOk();

        expect(collect($reponse->json('data'))->pluck('id')->all())->toBe([$mien->id]);
    });

    it('est vide pour un prestataire sans retrait', function () {
        prestataireConsultant();

        $this->getJson('/api/v1/prestataire/retraits')->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('détail et confidentialité', function () {
    it('renvoie les champs prévus, avec le numéro masqué', function () {
        $prestataire = prestataireConsultant();
        $retrait = Retrait::factory()->reussi()->create(['prestataire_id' => $prestataire->id, 'montant' => '7500.00', 'telephone_versement' => '+221771234567']);

        $this->getJson("/api/v1/prestataire/retraits/{$retrait->id}")->assertOk()->assertExactJson([
            'id' => $retrait->id,
            'montant' => 7500.0,
            'statut' => 'reussi',
            'telephoneVersement' => '+221******567',
            'dateDemande' => $retrait->fresh()->created_at->toIso8601String(),
            'dateTraitement' => $retrait->fresh()->traite_le->toIso8601String(),
        ]);
    });

    it('a une date de traitement vide tant que le retrait est en cours', function () {
        $prestataire = prestataireConsultant();
        $retrait = Retrait::factory()->create(['prestataire_id' => $prestataire->id]);

        $this->getJson("/api/v1/prestataire/retraits/{$retrait->id}")->assertOk()
            ->assertJsonPath('statut', 'en_cours')->assertJsonPath('dateTraitement', null);
    });

    it('n\'expose jamais la référence du prestataire de paiement ni la garde de recrédit', function () {
        $prestataire = prestataireConsultant();
        $retrait = Retrait::factory()->echoue()->create(['prestataire_id' => $prestataire->id, 'recredite_le' => now(), 'telephone_versement' => '+221771234567']);

        $contenu = $this->getJson("/api/v1/prestataire/retraits/{$retrait->id}")->assertOk()->getContent();
        $liste = $this->getJson('/api/v1/prestataire/retraits')->assertOk()->getContent();

        foreach ([$contenu, $liste] as $texte) {
            expect($texte)->not->toContain($retrait->reference_dexpay)
                ->not->toContain('reference')->not->toContain('recredit')->not->toContain('771234567');
        }
    });

    it('répond 404 identique pour un retrait inconnu, mal formé ou d\'un autre prestataire', function () {
        $autre = Retrait::factory()->create();
        prestataireConsultant();

        foreach ([(string) Str::uuid(), 'pas-un-uuid', $autre->id] as $id) {
            $this->getJson("/api/v1/prestataire/retraits/{$id}")->assertNotFound()->assertExactJson(['message' => 'Retrait introuvable.']);
        }
    });

    it('masque le numéro sauf les 3 derniers chiffres', function (string $numero, string $attendu) {
        expect(RetraitResource::masquer($numero))->toBe($attendu);
    })->with([
        ['+221771234567', '+221******567'],
        ['771234567', '******567'],
        ['123', '123'],
    ]);
});
