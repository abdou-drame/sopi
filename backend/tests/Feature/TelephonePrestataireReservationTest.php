<?php

use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const TELEPHONE_PRESTATAIRE = '+221771234567';

function reservationAvecEtat(string $etat, ?User $client = null, string $telephone = TELEPHONE_PRESTATAIRE): array
{
    $client ??= User::factory()->create();
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire()->state(['telephone' => $telephone]), 'user')->create();
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create();
    $reservation = Reservation::factory()->{$etat}()->create(['client_id' => $client->id, 'creneau_id' => $creneau->id]);
    Sanctum::actingAs($client);

    return [$reservation, $prestataire];
}

describe('téléphone du prestataire dans le détail client', function () {
    it('est communiqué une fois la réservation confirmée, en cours ou terminée', function (string $etat) {
        [$reservation] = reservationAvecEtat($etat);

        $this->getJson('/api/v1/reservations/'.$reservation->id)->assertOk()->assertJsonPath('prestataire.telephone', TELEPHONE_PRESTATAIRE);
    })->with(['confirmee', 'enCours', 'terminee']);

    it('reste null avant la confirmation et pour une réservation refusée ou annulée', function (string $etat) {
        [$reservation] = reservationAvecEtat($etat);

        $reponse = $this->getJson('/api/v1/reservations/'.$reservation->id)->assertOk()->assertJsonPath('prestataire.telephone', null);
        expect($reponse->getContent())->not->toContain('771234567');
    })->with(['enAttente', 'acceptee', 'refusee', 'annulee']);

    it('suit la même règle dans la liste des réservations', function () {
        [$confirmee] = reservationAvecEtat('confirmee', $client = User::factory()->create());
        [$attente] = reservationAvecEtat('enAttente', $client, '+221779999999');

        $lignes = collect($this->getJson('/api/v1/reservations')->assertOk()->json('data'))->keyBy('id');

        expect($lignes[$confirmee->id]['prestataire']['telephone'])->toBe(TELEPHONE_PRESTATAIRE)
            ->and($lignes[$attente->id]['prestataire']['telephone'])->toBeNull();
    });
});
