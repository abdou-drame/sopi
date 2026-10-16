<?php

use App\Enums\StatutPaiement;
use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Réservation EN_COURS payée : prix, acompte et commission figés (30 % / 10 % de 15 000 par défaut). */
function reservationPayeeEnCours(Prestataire $prestataire, string $prix = '15000.00', string $acompte = '4500.00', string $commission = '1500.00'): Reservation
{
    $reservation = Reservation::factory()->enCours()->create([
        'client_id' => User::factory()->create()->id,
        'creneau_id' => Creneau::factory()->for($prestataire)->reserve()->create()->id,
        'prix_applique' => $prix,
    ]);
    Paiement::factory()->reussi()->create(['reservation_id' => $reservation->id, 'montant_acompte' => $acompte, 'commission' => $commission]);

    return $reservation;
}

describe('POST /prestataire/reservations/{id}/terminer : montants', function () {
    it('renvoie le total, la commission, le montant crédité et le solde après, égaux à ce qui est en base', function () {
        $prestataire = Prestataire::factory()->avoirSolde(2000)->for(User::factory()->prestataire(), 'user')->create();
        $this->actingAs($prestataire->user, 'sanctum');
        $reservation = reservationPayeeEnCours($prestataire);

        $reponse = $this->postJson("/api/v1/prestataire/reservations/{$reservation->id}/terminer")->assertOk()
            ->assertJsonPath('statut', 'terminee')
            ->assertJsonPath('montantTotal', 15000)
            ->assertJsonPath('commission', 1500)
            ->assertJsonPath('montantCredite', 3000)
            ->assertJsonPath('soldeApres', 5000);

        expect((float) $prestataire->fresh()->solde)->toBe((float) $reponse->json('soldeApres'))
            ->and((float) $prestataire->fresh()->solde - 2000)->toBe((float) $reponse->json('montantCredite'));
    });

    it('suit les montants du paiement réellement enregistré, même avec d\'autres taux', function () {
        $prestataire = Prestataire::factory()->avoirSolde(0)->for(User::factory()->prestataire(), 'user')->create();
        $this->actingAs($prestataire->user, 'sanctum');
        $reservation = reservationPayeeEnCours($prestataire, '8000.00', '2000.00', '800.00');

        $this->postJson("/api/v1/prestataire/reservations/{$reservation->id}/terminer")->assertOk()
            ->assertJsonPath('montantTotal', 8000)
            ->assertJsonPath('commission', 800)
            ->assertJsonPath('montantCredite', 1200)
            ->assertJsonPath('soldeApres', 1200);
    });

    it('renvoie des montants nuls (null) s\'il n\'y a pas de paiement réussi, sans rien créditer', function () {
        $prestataire = Prestataire::factory()->avoirSolde(700)->for(User::factory()->prestataire(), 'user')->create();
        $this->actingAs($prestataire->user, 'sanctum');
        $reservation = reservationPayeeEnCours($prestataire);
        Paiement::query()->where('reservation_id', $reservation->id)->update(['statut' => StatutPaiement::Echoue->value]);

        $this->postJson("/api/v1/prestataire/reservations/{$reservation->id}/terminer")->assertOk()
            ->assertJsonPath('montantTotal', 15000)
            ->assertJsonPath('commission', null)
            ->assertJsonPath('montantCredite', null)
            ->assertJsonPath('soldeApres', null);
        expect((float) $prestataire->fresh()->solde)->toBe(700.0);
    });

    it('ne change pas le crédit : un second appel refuse en 409 et le solde ne bouge plus', function () {
        $prestataire = Prestataire::factory()->avoirSolde(0)->for(User::factory()->prestataire(), 'user')->create();
        $this->actingAs($prestataire->user, 'sanctum');
        $reservation = reservationPayeeEnCours($prestataire);

        $this->postJson("/api/v1/prestataire/reservations/{$reservation->id}/terminer")->assertOk();
        $this->postJson("/api/v1/prestataire/reservations/{$reservation->id}/terminer")->assertStatus(409);

        expect((float) $prestataire->fresh()->solde)->toBe(3000.0);
    });
});
