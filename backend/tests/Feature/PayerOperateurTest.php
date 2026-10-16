<?php

use App\Enums\StatutReservation;
use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Paiement\PaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['sopi.paiement.driver' => 'fake']);
    app()->forgetInstance(PaiementService::class);
});

/** Réservation ACCEPTEE du client connecté, prix 15 000 FCFA. */
function reservationAcceptee(): Reservation
{
    $client = User::factory()->create();
    test()->actingAs($client, 'sanctum');
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $debut = now()->addDay();

    return Reservation::factory()->create([
        'client_id' => $client->id,
        'creneau_id' => Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()])->id,
        'prestation_id' => Prestation::factory()->for($prestataire)->create(['prix' => '15000.00'])->id,
        'statut' => StatutReservation::Acceptee,
        'acceptee_le' => now(),
        'prix_applique' => '15000.00',
    ]);
}

describe('POST /reservations/{id}/payer : operateur', function () {
    it('garde le comportement actuel sans operateur', function () {
        $reservation = reservationAcceptee();

        $this->postJson("/api/v1/reservations/{$reservation->id}/payer")->assertCreated()
            ->assertJsonPath('paiement.montantAcompte', '4500.00');

        expect(Paiement::query()->sole()->operateur_souhaite)->toBeNull();
    });

    it('accepte wave et orange_money, les enregistre sans changer le montant ni la réponse', function (string $operateur) {
        $reservation = reservationAcceptee();

        $this->postJson("/api/v1/reservations/{$reservation->id}/payer", ['operateur' => $operateur])->assertCreated()
            ->assertJsonPath('paiement.montantAcompte', '4500.00')
            ->assertJsonMissingPath('paiement.operateur');

        expect(Paiement::query()->sole()->operateur_souhaite)->toBe($operateur);
    })->with(['wave', 'orange_money']);

    it('refuse un opérateur inconnu en 422, sans créer de paiement', function () {
        $reservation = reservationAcceptee();

        $this->postJson("/api/v1/reservations/{$reservation->id}/payer", ['operateur' => 'mtn'])->assertUnprocessable()
            ->assertJsonValidationErrors('operateur');

        expect(Paiement::query()->count())->toBe(0);
    });
});
