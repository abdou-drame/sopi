<?php

use App\Enums\StatutCompte;
use App\Models\Prestataire;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('POST /auth/connexion : refus structuré', function () {
    it('ajoute motif et finSuspension (ISO 8601) pour un compte suspendu, en gardant le message', function () {
        $fin = Carbon::parse('2030-03-10 14:30:00', 'UTC');
        $user = User::factory()->prestataire()->statut(StatutCompte::Suspendu)->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);
        Sanction::factory()->suspension($fin)->create(['user_id' => $user->id, 'motif' => 'Retards répétés.']);

        $reponse = $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertForbidden()
            ->assertJsonPath('motif', 'Retards répétés.');

        expect($reponse->json('message'))->toContain('Votre compte est suspendu jusqu\'au')->toContain('Retards répétés.')
            ->and(Carbon::parse($reponse->json('finSuspension'))->equalTo($fin))->toBeTrue()
            ->and($reponse->json('finSuspension'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
    });

    it('ajoute le motif sans date de fin pour un compte banni', function () {
        $user = User::factory()->prestataire()->statut(StatutCompte::Banni)->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);
        Sanction::factory()->bannissement()->create(['user_id' => $user->id, 'motif' => 'Fraude.']);

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertForbidden()
            ->assertJsonPath('motif', 'Fraude.')
            ->assertJsonMissingPath('finSuspension');
    });

    it('ajoute le motif du refus d\'inscription pour un compte refusé', function () {
        $prestataire = Prestataire::factory()->refuse('Pièces illisibles.')->create();
        $prestataire->user->forceFill(['telephone' => '+221771234567', 'password' => 'motdepasse1'])->save();

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Votre demande d\'inscription a été refusée.')
            ->assertJsonPath('motif', 'Pièces illisibles.')
            ->assertJsonMissingPath('finSuspension');
    });

    it('n\'ajoute aucun motif pour un compte en attente de validation (seulement statutCompte)', function () {
        User::factory()->prestataire()->statut(StatutCompte::EnAttenteValidation)->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Votre compte est en attente de validation.', 'statutCompte' => 'en_attente']);
    });

    it('ajoute statutCompte (valeur de l\'énumération) pour chaque statut non actif', function (StatutCompte $statut) {
        User::factory()->prestataire()->statut($statut)->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertForbidden()
            ->assertJsonPath('statutCompte', $statut->value);
    })->with([StatutCompte::EnAttenteValidation, StatutCompte::Refuse, StatutCompte::Suspendu, StatutCompte::Banni]);

    it('garde message, motif et finSuspension inchangés à côté de statutCompte', function () {
        $fin = Carbon::parse('2030-03-10 14:30:00', 'UTC');
        $user = User::factory()->prestataire()->statut(StatutCompte::Suspendu)->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);
        Sanction::factory()->suspension($fin)->create(['user_id' => $user->id, 'motif' => 'Retards répétés.']);

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertForbidden()
            ->assertJsonPath('statutCompte', 'suspendu')
            ->assertJsonPath('motif', 'Retards répétés.')
            ->assertJsonStructure(['message', 'statutCompte', 'motif', 'finSuspension']);
    });

    it('ne met pas statutCompte dans une connexion réussie', function () {
        User::factory()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertOk()->assertJsonMissingPath('statutCompte');
    });
});
