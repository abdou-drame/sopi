<?php

use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** Réservation du client sur un créneau du prestataire, de $debut à $debut + 1 h, dans l'état donné (méthode de la factory). */
function reservationGroupe(User $client, Prestataire $prestataire, string $etat, string $debut): Reservation
{
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create([
        'date_debut' => now()->parse($debut), 'date_fin' => now()->parse($debut)->addHour(),
    ]);

    return Reservation::factory()->{$etat}()->create(['client_id' => $client->id, 'creneau_id' => $creneau->id]);
}

/** @return list<string> identifiants de la réponse, dans l'ordre */
function idsListe(string $url): array
{
    return collect(test()->getJson($url)->assertOk()->json('data'))->pluck('id')->all();
}

beforeEach(function () {
    $this->client = User::factory()->create();
    $this->prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
});

describe('GET /reservations?groupe=', function () {
    beforeEach(function () {
        $this->travelTo(now()->parse('2030-06-15 12:00:00'));
        $this->r = [
            'attente_tard' => reservationGroupe($this->client, $this->prestataire, 'enAttente', '2030-06-20 10:00:00'),
            'acceptee' => reservationGroupe($this->client, $this->prestataire, 'acceptee', '2030-06-17 10:00:00'),
            'confirmee' => reservationGroupe($this->client, $this->prestataire, 'confirmee', '2030-06-16 10:00:00'),
            'en_cours' => reservationGroupe($this->client, $this->prestataire, 'enCours', '2030-06-15 11:30:00'),
            'confirmee_passee' => reservationGroupe($this->client, $this->prestataire, 'confirmee', '2030-06-10 10:00:00'),
            'terminee_ancienne' => reservationGroupe($this->client, $this->prestataire, 'terminee', '2030-06-01 10:00:00'),
            'terminee_recente' => reservationGroupe($this->client, $this->prestataire, 'terminee', '2030-06-12 10:00:00'),
            'annulee' => reservationGroupe($this->client, $this->prestataire, 'annulee', '2030-06-18 10:00:00'),
            'refusee' => reservationGroupe($this->client, $this->prestataire, 'refusee', '2030-06-19 10:00:00'),
        ];
        // Réservation d'un autre client : jamais visible.
        reservationGroupe(User::factory()->create(), $this->prestataire, 'confirmee', '2030-06-21 10:00:00');
        Sanctum::actingAs($this->client);
    });

    it('a_venir : statuts actifs au créneau non terminé, par date de créneau croissante', function () {
        $r = $this->r;

        expect(idsListe('/api/v1/reservations?groupe=a_venir'))->toBe([
            $r['en_cours']->id, $r['confirmee']->id, $r['acceptee']->id, $r['attente_tard']->id,
        ]);
    });

    it('terminees : réservations terminées, les plus récentes d\'abord', function () {
        $r = $this->r;

        expect(idsListe('/api/v1/reservations?groupe=terminees'))->toBe([$r['terminee_recente']->id, $r['terminee_ancienne']->id]);
    });

    it('annulees : annulées et refusées, les plus récentes d\'abord', function () {
        $r = $this->r;

        expect(idsListe('/api/v1/reservations?groupe=annulees'))->toBe([$r['refusee']->id, $r['annulee']->id]);
    });

    it('garde le filtre statut seul et peut le combiner avec un groupe', function () {
        $r = $this->r;

        expect(idsListe('/api/v1/reservations?statut=terminee'))->toHaveCount(2)
            ->and(idsListe('/api/v1/reservations?groupe=a_venir&statut=confirmee'))->toBe([$r['confirmee']->id])
            ->and(idsListe('/api/v1/reservations?groupe=terminees&statut=annulee'))->toBe([])
            ->and(idsListe('/api/v1/reservations'))->toHaveCount(9);
    });

    it('refuse un groupe inconnu en français', function () {
        $this->getJson('/api/v1/reservations?groupe=zzz')->assertStatus(422)
            ->assertJsonPath('errors.groupe.0', 'Le groupe doit être l\'un de : a_venir, terminees, annulees.');
    });

    it('pagine les groupes par 20 sans doublon', function () {
        for ($i = 0; $i < 25; $i++) {
            reservationGroupe($this->client, $this->prestataire, 'terminee', now()->parse('2030-05-01 00:00:00')->addHours($i * 2)->toDateTimeString());
        }
        $page1 = idsListe('/api/v1/reservations?groupe=terminees');
        $page2 = idsListe('/api/v1/reservations?groupe=terminees&page=2');

        expect($page1)->toHaveCount(20)->and($page2)->toHaveCount(7)->and(array_intersect($page1, $page2))->toBe([]);
    });
});

describe('GET /prestataire/reservations?groupe=', function () {
    beforeEach(function () {
        $this->travelTo(now()->parse('2030-06-15 12:00:00'));
        $this->r = [
            'confirmee' => reservationGroupe($this->client, $this->prestataire, 'confirmee', '2030-06-16 10:00:00'),
            'attente' => reservationGroupe($this->client, $this->prestataire, 'enAttente', '2030-06-18 10:00:00'),
            'passee' => reservationGroupe($this->client, $this->prestataire, 'acceptee', '2030-06-10 10:00:00'),
            'terminee' => reservationGroupe($this->client, $this->prestataire, 'terminee', '2030-06-12 10:00:00'),
            'refusee' => reservationGroupe($this->client, $this->prestataire, 'refusee', '2030-06-19 10:00:00'),
        ];
        // Demande d'un autre prestataire : jamais visible.
        $autre = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
        reservationGroupe($this->client, $autre, 'confirmee', '2030-06-17 10:00:00');
        Sanctum::actingAs($this->prestataire->user);
    });

    it('applique les mêmes groupes et le même ordre', function () {
        $r = $this->r;

        expect(idsListe('/api/v1/prestataire/reservations?groupe=a_venir'))->toBe([$r['confirmee']->id, $r['attente']->id])
            ->and(idsListe('/api/v1/prestataire/reservations?groupe=terminees'))->toBe([$r['terminee']->id])
            ->and(idsListe('/api/v1/prestataire/reservations?groupe=annulees'))->toBe([$r['refusee']->id])
            ->and(idsListe('/api/v1/prestataire/reservations?statut=refusee'))->toBe([$r['refusee']->id]);
    });

    it('refuse un groupe inconnu', function () {
        $this->getJson('/api/v1/prestataire/reservations?groupe=zzz')->assertStatus(422)->assertJsonValidationErrors('groupe');
    });
});
