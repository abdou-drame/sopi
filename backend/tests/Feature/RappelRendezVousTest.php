<?php

use App\Enums\StatutReservation;
use App\Enums\TypeNotification;
use App\Models\Creneau;
use App\Models\Notification;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

/** Réservation dont le créneau commence dans $minutes minutes (négatif : déjà commencé). Client Awa Diop, prestataire Garage Sow. */
function reservationPourRappel(int $minutes, string $etat = 'confirmee'): array
{
    $client = User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diop', 'jeton_push' => 'jeton-client']);
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire()->state(['jeton_push' => 'jeton-prestataire']), 'user')->create(['nom_entreprise' => 'Garage Sow']);
    $prestation = Prestation::factory()->for($prestataire)->create(['nom' => 'Vidange', 'prix' => '15000.00']);
    $debut = now()->addMinutes($minutes);
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
    $reservation = Reservation::factory()->{$etat}()->create(['client_id' => $client->id, 'creneau_id' => $creneau->id, 'prestation_id' => $prestation->id]);

    return [$client, $prestataire, $reservation];
}

function rappelsDe(User $user)
{
    return Notification::query()->where('user_id', $user->id)->where('type', TypeNotification::RappelRendezVous)->get();
}

it('envoie le rappel au client et au prestataire, sans révéler l\'identité complète du client', function () {
    [$client, $prestataire, $reservation] = reservationPourRappel(90);

    $this->artisan('sopi:reservations:rappeler')->assertSuccessful();

    $pourClient = rappelsDe($client);
    $pourPrestataire = rappelsDe($prestataire->user);
    expect($pourClient)->toHaveCount(1)
        ->and($pourPrestataire)->toHaveCount(1)
        ->and($pourClient->first()->corps)->toContain('Vidange')->toContain('Garage Sow')
        ->and($pourPrestataire->first()->corps)->toContain('Awa D.')->not->toContain('Diop')
        ->and($pourClient->first()->donnees)->toBe(['reservationId' => $reservation->id])
        ->and($reservation->fresh()->rappel_envoye_le)->not->toBeNull();
});

it('n\'envoie le rappel qu\'une seule fois', function () {
    [$client, $prestataire] = reservationPourRappel(60);

    $this->artisan('sopi:reservations:rappeler')->assertSuccessful();
    $this->artisan('sopi:reservations:rappeler')->assertSuccessful();

    expect(rappelsDe($client))->toHaveCount(1)->and(rappelsDe($prestataire->user))->toHaveCount(1);
});

it('respecte la fenêtre : rien au-delà de 2 h ni pour un créneau déjà commencé', function (int $minutes) {
    [$client, $prestataire, $reservation] = reservationPourRappel($minutes);

    $this->artisan('sopi:reservations:rappeler')->assertSuccessful();

    expect(rappelsDe($client))->toHaveCount(0)
        ->and(rappelsDe($prestataire->user))->toHaveCount(0)
        ->and($reservation->fresh()->rappel_envoye_le)->toBeNull();
})->with(['dans 3 h' => 180, 'dans 2 h 05' => 125, 'déjà commencé' => -10]);

it('envoie le rappel à la limite de la fenêtre (2 h au plus)', function () {
    [$client] = reservationPourRappel(119);

    $this->artisan('sopi:reservations:rappeler')->assertSuccessful();

    expect(rappelsDe($client))->toHaveCount(1);
});

it('ignore les réservations qui ne sont pas confirmées', function (string $etat) {
    [$client, $prestataire] = reservationPourRappel(60, $etat);

    $this->artisan('sopi:reservations:rappeler')->assertSuccessful();

    expect(rappelsDe($client))->toHaveCount(0)->and(rappelsDe($prestataire->user))->toHaveCount(0);
})->with(['enAttente', 'acceptee', 'annulee', 'refusee', 'enCours', 'terminee']);

it('n\'envoie plus de rappel pour une réservation annulée après coup', function () {
    [$client, , $reservation] = reservationPourRappel(60);
    $reservation->forceFill(['statut' => StatutReservation::Annulee])->save();

    $this->artisan('sopi:reservations:rappeler')->assertSuccessful();

    expect(rappelsDe($client))->toHaveCount(0);
});
