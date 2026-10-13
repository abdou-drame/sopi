<?php

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationTerminee;
use App\Exceptions\CreditSoldeImpossibleException;
use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CreditSoldeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/** Prestataire actif connecté à l'API, avec un solde de départ. */
function prestataireACrediter(string|int|float $solde = 0): Prestataire
{
    $prestataire = Prestataire::factory()->avoirSolde($solde)->for(User::factory()->prestataire(), 'user')->create();
    test()->actingAs($prestataire->user, 'sanctum');

    return $prestataire;
}

/** Réservation EN_COURS d'un créneau du prestataire, avec (ou sans) un paiement du statut demandé. */
function reservationACrediter(Prestataire $prestataire, ?StatutPaiement $statutPaiement = StatutPaiement::Reussi, string $acompte = '3000.00', string $commission = '1000.00'): Reservation
{
    $reservation = Reservation::factory()->enCours()->create([
        'client_id' => User::factory()->create()->id,
        'creneau_id' => Creneau::factory()->for($prestataire)->reserve()->create()->id,
    ]);

    if ($statutPaiement !== null) {
        $etat = match ($statutPaiement) {
            StatutPaiement::EnAttente => 'enAttente',
            StatutPaiement::Reussi => 'reussi',
            StatutPaiement::Echoue => 'echoue',
            StatutPaiement::Rembourse => 'rembourse',
        };
        Paiement::factory()->{$etat}()->create(['reservation_id' => $reservation->id, 'montant_acompte' => $acompte, 'commission' => $commission]);
    }

    return $reservation;
}

function terminerReservation(Reservation $reservation)
{
    return test()->postJson("/api/v1/prestataire/reservations/{$reservation->id}/terminer");
}

describe('fin de prestation via l\'API', function () {
    it('crédite le solde de acompte − commission, pose la garde et publie l\'événement', function () {
        Event::fake([ReservationTerminee::class]);
        $prestataire = prestataireACrediter(500);
        $reservation = reservationACrediter($prestataire);

        terminerReservation($reservation)->assertOk()->assertJsonPath('statut', 'terminee');

        expect($prestataire->fresh()->solde)->toBe('2500.00')
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Terminee)
            ->and($reservation->paiements()->first()->credite_le)->not->toBeNull();
        Event::assertDispatched(ReservationTerminee::class);
    });

    it('ne crédite qu\'une fois : la seconde fin de prestation est refusée et le solde ne bouge pas', function () {
        $prestataire = prestataireACrediter();
        $reservation = reservationACrediter($prestataire);

        terminerReservation($reservation)->assertOk();
        terminerReservation($reservation)->assertStatus(409);

        expect($prestataire->fresh()->solde)->toBe('2000.00');
    });

    it('ne crédite que le prestataire de la réservation', function () {
        $prestataire = prestataireACrediter();
        $autre = Prestataire::factory()->avoirSolde(700)->create();
        $reservation = reservationACrediter($prestataire);

        terminerReservation($reservation)->assertOk();

        expect($prestataire->fresh()->solde)->toBe('2000.00')
            ->and($autre->fresh()->solde)->toBe('700.00');
    });

    it('termine quand même la prestation sans paiement réussi, avec un avertissement et sans crédit', function () {
        Log::spy();
        $prestataire = prestataireACrediter();
        $reservation = reservationACrediter($prestataire, null);

        terminerReservation($reservation)->assertOk()->assertJsonPath('statut', 'terminee');

        expect($prestataire->fresh()->solde)->toBe('0.00');
        Log::shouldHaveReceived('warning')->once();
    });

    it('est atomique : si le crédit échoue, la réservation reste EN_COURS, la garde n\'est pas posée et aucun événement n\'est publié', function () {
        Event::fake([ReservationTerminee::class]);
        $prestataire = prestataireACrediter(500);
        $reservation = reservationACrediter($prestataire);
        DB::listen(function ($requete) {
            if (str_starts_with($requete->sql, 'update "prestataires"')) {
                throw new RuntimeException('Panne simulée : détail technique secret');
            }
        });

        terminerReservation($reservation)->assertStatus(500)
            ->assertExactJson(['message' => 'La fin de la prestation n\'a pas pu être enregistrée. Veuillez réessayer.']);

        expect($reservation->fresh()->statut)->toBe(StatutReservation::EnCours)
            ->and($reservation->paiements()->first()->credite_le)->toBeNull()
            ->and($prestataire->fresh()->solde)->toBe('500.00');
        Event::assertNotDispatched(ReservationTerminee::class);
    });

    it('est atomique dans l\'autre sens : si la transition échoue, le solde n\'est pas touché', function () {
        $prestataire = prestataireACrediter();
        $reservation = reservationACrediter($prestataire);
        Reservation::query()->whereKey($reservation->id)->update(['statut' => StatutReservation::Terminee->value]);

        terminerReservation($reservation)->assertStatus(409);

        expect($prestataire->fresh()->solde)->toBe('0.00')
            ->and($reservation->paiements()->first()->credite_le)->toBeNull();
    });
});

describe('CreditSoldeService', function () {
    it('ne crédite pas un paiement qui n\'est pas réussi', function (StatutPaiement $statut) {
        $prestataire = Prestataire::factory()->create();
        $reservation = reservationACrediter($prestataire, $statut);

        expect(app(CreditSoldeService::class)->crediter($reservation))->toBeFalse();

        expect($prestataire->fresh()->solde)->toBe('0.00')
            ->and($reservation->paiements()->first()->credite_le)->toBeNull();
    })->with([StatutPaiement::EnAttente, StatutPaiement::Echoue, StatutPaiement::Rembourse]);

    it('crédite une seule fois sur des appels répétés', function () {
        $prestataire = Prestataire::factory()->create();
        $reservation = reservationACrediter($prestataire);
        $service = app(CreditSoldeService::class);

        expect($service->crediter($reservation))->toBeTrue()
            ->and($service->crediter($reservation))->toBeFalse()
            ->and($service->crediter($reservation))->toBeFalse();

        expect($prestataire->fresh()->solde)->toBe('2000.00');
    });

    it('ne crédite pas deux fois quand deux appels travaillent sur une lecture périmée (concurrence simulée)', function () {
        $prestataire = Prestataire::factory()->create();
        $reservation = reservationACrediter($prestataire);
        // Deux « processus » ont lu la même réservation avant que l'un d'eux n'écrive.
        $copieA = Reservation::query()->findOrFail($reservation->id);
        $copieB = Reservation::query()->findOrFail($reservation->id);
        $paiementPerime = Paiement::query()->where('reservation_id', $reservation->id)->firstOrFail();
        $service = app(CreditSoldeService::class);

        expect($service->crediter($copieA))->toBeTrue();
        // La copie périmée du paiement croit encore pouvoir créditer : la garde conditionnelle en base la refuse.
        expect($paiementPerime->crediterSiPossible())->toBeFalse()
            ->and($service->crediter($copieB))->toBeFalse();

        expect($prestataire->fresh()->solde)->toBe('2000.00');
    });

    it('ne crédite pas un paiement dont la garde est déjà posée', function () {
        $prestataire = Prestataire::factory()->create();
        $reservation = reservationACrediter($prestataire);
        DB::table('paiements')->where('reservation_id', $reservation->id)->update(['credite_le' => now()]);

        expect(app(CreditSoldeService::class)->crediter($reservation))->toBeFalse();

        expect($prestataire->fresh()->solde)->toBe('0.00');
    });

    it('calcule exactement les petits montants', function (string $acompte, string $commission, string $attendu) {
        $prestataire = Prestataire::factory()->avoirSolde('10.10')->create();
        $reservation = reservationACrediter($prestataire, StatutPaiement::Reussi, $acompte, $commission);

        expect(app(CreditSoldeService::class)->crediter($reservation))->toBeTrue();

        expect($prestataire->fresh()->solde)->toBe($attendu);
    })->with([
        'petit montant' => ['300.00', '100.00', '210.10'],
        'commission égale à l\'acompte' => ['500.00', '500.00', '10.10'],
        'commission nulle' => ['1.00', '0.00', '11.10'],
        'centimes' => ['100.50', '33.33', '77.27'],
    ]);

    it('cumule les crédits de plusieurs prestations', function () {
        $prestataire = Prestataire::factory()->create();
        foreach (range(1, 3) as $i) {
            app(CreditSoldeService::class)->crediter(reservationACrediter($prestataire));
        }

        expect($prestataire->fresh()->solde)->toBe('6000.00');
    });

    it('lève une exception française et générique si la base échoue', function () {
        $prestataire = Prestataire::factory()->create();
        $reservation = reservationACrediter($prestataire);
        DB::listen(function ($requete) {
            if (str_starts_with($requete->sql, 'update "prestataires"')) {
                throw new RuntimeException('Panne simulée');
            }
        });

        expect(fn () => app(CreditSoldeService::class)->crediter($reservation))->toThrow(CreditSoldeImpossibleException::class);

        expect($reservation->paiements()->first()->credite_le)->toBeNull()
            ->and($prestataire->fresh()->solde)->toBe('0.00');
    });
});
