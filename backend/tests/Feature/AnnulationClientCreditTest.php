<?php

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Enums\TypeNotification;
use App\Models\Creneau;
use App\Models\Notification;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CreditSoldeService;
use App\Services\RevenusAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** Réservation d'un client connecté, créneau dans $heures heures ; paiement du statut demandé (ou aucun). */
function reservationAnnulableAvecPaiement(StatutReservation $statut, ?StatutPaiement $paiement, float|int $heures = 24, string|int $solde = 500): array
{
    $client = User::factory()->create();
    test()->actingAs($client, 'sanctum');
    $prestataire = Prestataire::factory()->avoirSolde($solde)->for(User::factory()->prestataire(), 'user')->create();
    $debut = now()->addMinutes((int) round($heures * 60));
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
    $extra = $statut === StatutReservation::EnAttente ? [] : ['acceptee_le' => now()];
    if ($statut === StatutReservation::Confirmee) {
        $extra['code_reservation'] = 'ABC123';
    }
    $reservation = Reservation::factory()->create(['client_id' => $client->id, 'creneau_id' => $creneau->id, 'statut' => $statut] + $extra);

    $p = null;
    if ($paiement !== null) {
        $etat = match ($paiement) {
            StatutPaiement::EnAttente => 'enAttente',
            StatutPaiement::Reussi => 'reussi',
            StatutPaiement::Echoue => 'echoue',
            StatutPaiement::Rembourse => 'rembourse',
        };
        $p = Paiement::factory()->{$etat}()->create(['reservation_id' => $reservation->id, 'montant_acompte' => '3000.00', 'commission' => '1000.00']);
    }

    return [$reservation, $prestataire, $p];
}

function annulerCommeClient(Reservation $reservation)
{
    return test()->postJson("/api/v1/reservations/{$reservation->id}/annuler");
}

function mouvementsDe(Prestataire $prestataire)
{
    return DB::table('mouvements_portefeuille')->where('prestataire_id', $prestataire->id)->get();
}

describe('annulation par le client d\'une réservation CONFIRMEE payée', function () {
    it('crédite le prestataire de acompte moins commission, une seule fois, et garde le paiement REUSSI', function () {
        [$reservation, $prestataire, $paiement] = reservationAnnulableAvecPaiement(StatutReservation::Confirmee, StatutPaiement::Reussi);

        annulerCommeClient($reservation)->assertOk()->assertJsonPath('statut', 'annulee');

        $mouvements = mouvementsDe($prestataire);
        expect((float) $prestataire->fresh()->solde)->toBe(2500.0)
            ->and($paiement->fresh()->statut)->toBe(StatutPaiement::Reussi)
            ->and($paiement->fresh()->credite_le)->not->toBeNull()
            ->and($paiement->fresh()->rembourse_le)->toBeNull()
            ->and($mouvements)->toHaveCount(1)
            ->and($mouvements[0]->libelle)->toBe('Annulation par le client (acompte moins commission)')
            ->and((float) $mouvements[0]->montant)->toBe(2000.0)
            ->and($mouvements[0]->reservation_id)->toBe($reservation->id);
    });

    it('ne crédite jamais deux fois : une seconde annulation est refusée et le crédit manuel répété est sans effet', function () {
        [$reservation, $prestataire] = reservationAnnulableAvecPaiement(StatutReservation::Confirmee, StatutPaiement::Reussi);

        annulerCommeClient($reservation)->assertOk();
        annulerCommeClient($reservation)->assertStatus(409);
        expect(app(CreditSoldeService::class)->crediter($reservation->fresh()))->toBeFalse();

        expect((float) $prestataire->fresh()->solde)->toBe(2500.0)
            ->and(mouvementsDe($prestataire))->toHaveCount(1);
    });

    it('n\'écrit rien quand l\'annulation est refusée à moins de 2 h du créneau', function () {
        [$reservation, $prestataire, $paiement] = reservationAnnulableAvecPaiement(StatutReservation::Confirmee, StatutPaiement::Reussi, 1);

        annulerCommeClient($reservation)->assertStatus(409);

        expect((float) $prestataire->fresh()->solde)->toBe(500.0)
            ->and($paiement->fresh()->credite_le)->toBeNull()
            ->and(mouvementsDe($prestataire))->toHaveCount(0)
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
    });

    it('annule tout ensemble : si le crédit échoue, la réservation reste CONFIRMEE', function () {
        [$reservation, $prestataire, $paiement] = reservationAnnulableAvecPaiement(StatutReservation::Confirmee, StatutPaiement::Reussi);
        $this->withoutExceptionHandling();
        app()->bind(CreditSoldeService::class, fn () => new class extends CreditSoldeService
        {
            public function crediterAvecDetail(Reservation $reservation, string $libelle = self::LIBELLE_PRESTATION_TERMINEE): ?\App\Services\CreditEffectue
            {
                throw new \App\Exceptions\CreditSoldeImpossibleException('boom');
            }
        });

        try {
            annulerCommeClient($reservation);
        } catch (\Throwable) {
        }

        expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee)
            ->and($paiement->fresh()->credite_le)->toBeNull()
            ->and((float) $prestataire->fresh()->solde)->toBe(500.0);
    });

    it('mentionne le montant crédité dans la notification au prestataire', function () {
        [$reservation, $prestataire] = reservationAnnulableAvecPaiement(StatutReservation::Confirmee, StatutPaiement::Reussi);

        annulerCommeClient($reservation)->assertOk();

        $n = Notification::query()->where('user_id', $prestataire->user_id)->where('type', TypeNotification::ReservationAnnulee->value)->sole();
        expect($n->corps)->toContain('2000')->toContain('crédités à votre solde');
    });

    it('fait entrer la commission dans les revenus de la plateforme, sans compter d\'acompte remboursé', function () {
        [$reservation] = reservationAnnulableAvecPaiement(StatutReservation::Confirmee, StatutPaiement::Reussi);

        annulerCommeClient($reservation)->assertOk();

        $revenus = app(RevenusAdminService::class)->revenus(now()->subDay(), now()->addDay(), 'jour');
        expect($revenus['totaux']['revenus'])->toBe(1000)
            ->and($revenus['totaux']['remboursements'])->toBe(0);
    });
});

describe('annulation par le client sans acompte payé', function () {
    it('ne crédite rien pour une réservation EN_ATTENTE ou ACCEPTEE sans paiement', function (StatutReservation $statut) {
        [$reservation, $prestataire] = reservationAnnulableAvecPaiement($statut, null);

        annulerCommeClient($reservation)->assertOk();

        expect((float) $prestataire->fresh()->solde)->toBe(500.0)
            ->and(mouvementsDe($prestataire))->toHaveCount(0)
            ->and(Notification::query()->where('user_id', $prestataire->user_id)->sole()->corps)->not->toContain('crédités');
    })->with([StatutReservation::EnAttente, StatutReservation::Acceptee]);

    it('ne crédite rien quand le paiement n\'est pas REUSSI', function (StatutPaiement $statutPaiement) {
        [$reservation, $prestataire, $paiement] = reservationAnnulableAvecPaiement(StatutReservation::Acceptee, $statutPaiement);

        annulerCommeClient($reservation)->assertOk();

        expect((float) $prestataire->fresh()->solde)->toBe(500.0)
            ->and($paiement->fresh()->credite_le)->toBeNull()
            ->and(mouvementsDe($prestataire))->toHaveCount(0);
    })->with([StatutPaiement::EnAttente, StatutPaiement::Echoue]);
});
