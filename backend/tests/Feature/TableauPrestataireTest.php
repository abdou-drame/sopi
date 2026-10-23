<?php

use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const URL_TABLEAU_PRESTATAIRE = '/api/v1/prestataire/tableau-de-bord';

function reservationTableau(User $client, Prestataire $prestataire, string $etat, string $debut): Reservation
{
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create([
        'date_debut' => now()->parse($debut), 'date_fin' => now()->parse($debut)->addHour(),
    ]);

    return Reservation::factory()->{$etat}()->create(['client_id' => $client->id, 'creneau_id' => $creneau->id]);
}

beforeEach(function () {
    $this->client = User::factory()->create();
    $this->prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $this->travelTo(now()->parse('2030-06-15 12:00:00'));
});

describe('GET /prestataire/tableau-de-bord', function () {
    it('compte les rendez-vous du jour et les terminées du mois, et liste le planning par heure', function () {
        $tard = reservationTableau($this->client, $this->prestataire, 'confirmee', '2030-06-15 16:00:00');
        $tot = reservationTableau($this->client, $this->prestataire, 'enCours', '2030-06-15 09:00:00');
        reservationTableau($this->client, $this->prestataire, 'acceptee', '2030-06-15 10:00:00');
        reservationTableau($this->client, $this->prestataire, 'confirmee', '2030-06-16 10:00:00');
        reservationTableau($this->client, $this->prestataire, 'terminee', '2030-06-02 10:00:00');
        reservationTableau($this->client, $this->prestataire, 'terminee', '2030-06-15 08:00:00');
        reservationTableau($this->client, $this->prestataire, 'terminee', '2030-05-30 10:00:00');
        // Autre prestataire : jamais compté.
        $autre = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
        reservationTableau($this->client, $autre, 'confirmee', '2030-06-15 11:00:00');
        reservationTableau($this->client, $autre, 'terminee', '2030-06-10 11:00:00');
        Sanctum::actingAs($this->prestataire->user);

        $reponse = $this->getJson(URL_TABLEAU_PRESTATAIRE)->assertOk()
            ->assertJsonPath('rendezVousAujourdhui', 2)
            ->assertJsonPath('terminesCeMois', 2)
            ->assertJsonCount(2, 'planningDuJour');

        expect(collect($reponse->json('planningDuJour'))->pluck('id')->all())->toBe([$tot->id, $tard->id]);
        $reponse->assertJsonPath('planningDuJour.0.client.initialeNom', mb_strtoupper(mb_substr($this->client->nom, 0, 1)))
            ->assertJsonMissingPath('planningDuJour.0.client.email')
            ->assertJsonMissingPath('planningDuJour.0.client.telephone');
    });

    it('répond avec des zéros et un planning vide sans rendez-vous', function () {
        Sanctum::actingAs($this->prestataire->user);

        $this->getJson(URL_TABLEAU_PRESTATAIRE)->assertOk()->assertExactJson([
            'rendezVousAujourdhui' => 0, 'terminesCeMois' => 0, 'planningDuJour' => [],
        ]);
    });

    it('refuse un client (403) et un visiteur (401)', function () {
        $this->getJson(URL_TABLEAU_PRESTATAIRE)->assertUnauthorized();
        Sanctum::actingAs($this->client);
        $this->getJson(URL_TABLEAU_PRESTATAIRE)->assertForbidden();
    });
});
