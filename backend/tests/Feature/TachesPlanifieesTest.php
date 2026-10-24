<?php

use App\Enums\CauseExpiration;
use App\Enums\StatutCreneau;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationExpiree;
use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2030-01-15 12:00:00'));
afterEach(fn () => Carbon::setTestNow());

/** Réservation sur un créneau RESERVE, créée il y a $minutes minutes. $attributs écrase les valeurs. */
function reservationAgee(StatutReservation $statut, int $minutes, array $attributs = []): Reservation
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create();
    $instant = now()->subMinutes($minutes);

    return Reservation::factory()->create(array_merge([
        'creneau_id' => $creneau->id,
        'statut' => $statut,
        'created_at' => $instant,
        'acceptee_le' => $statut === StatutReservation::EnAttente ? null : $instant,
    ], $attributs));
}

/** Créneau VERROUILLE par un client, dont le verrou expire dans $minutes minutes (négatif = déjà expiré). */
function creneauVerrouleExpirant(int $minutes): Creneau
{
    return Creneau::factory()->for(Prestataire::factory()->for(User::factory()->prestataire(), 'user'))->create([
        'statut' => StatutCreneau::Verrouille,
        'verrouille_par' => User::factory()->create()->id,
        'verrou_expire_a' => now()->addMinutes($minutes),
    ]);
}

function expect_creneau_libre(Creneau $creneau): void
{
    $creneau->refresh();
    expect($creneau->statut)->toBe(StatutCreneau::Libre)->and($creneau->verrou_expire_a)->toBeNull()->and($creneau->verrouille_par)->toBeNull();
}

describe('a) demandes sans réponse du prestataire (12 h)', function () {
    it('annule à 12 h 01, libère le créneau et publie l\'événement ; laisse intacte à 11 h 59', function () {
        Event::fake([ReservationExpiree::class]);
        $ancienne = reservationAgee(StatutReservation::EnAttente, 12 * 60 + 1);
        $recente = reservationAgee(StatutReservation::EnAttente, 11 * 60 + 59);

        $this->artisan('sopi:reservations:expirer-sans-reponse')->expectsOutput('1 réservation(s) annulée(s) faute de réponse du prestataire.')->assertSuccessful();

        expect($ancienne->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($recente->fresh()->statut)->toBe(StatutReservation::EnAttente)
            ->and($recente->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
        expect_creneau_libre($ancienne->creneau);
        Event::assertDispatchedTimes(ReservationExpiree::class, 1);
        Event::assertDispatched(ReservationExpiree::class, fn ($e) => $e->reservation->is($ancienne) && $e->cause === CauseExpiration::SansReponse);
    });

    it('ne touche jamais les autres statuts, même très anciens', function (StatutReservation $statut) {
        Event::fake([ReservationExpiree::class]);
        $reservation = reservationAgee($statut, 30 * 60);

        $this->artisan('sopi:reservations:expirer-sans-reponse')->assertSuccessful();

        expect($reservation->fresh()->statut)->toBe($statut)
            ->and($reservation->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
        Event::assertNothingDispatched();
    })->with([
        StatutReservation::Acceptee, StatutReservation::Confirmee, StatutReservation::EnCours,
        StatutReservation::Terminee, StatutReservation::Refusee, StatutReservation::Annulee,
    ]);

    it('perd la course si le prestataire a accepté juste avant : rien n\'est annulé, aucun événement', function () {
        Event::fake([ReservationExpiree::class]);
        $reservation = reservationAgee(StatutReservation::EnAttente, 13 * 60);
        // Entre la sélection du candidat et l'UPDATE conditionnel, le prestataire accepte.
        Reservation::retrieved(fn ($r) => DB::table('reservations')->where('id', $r->id)->update(['statut' => 'acceptee', 'acceptee_le' => now()]));

        $this->artisan('sopi:reservations:expirer-sans-reponse')->expectsOutput('0 réservation(s) annulée(s) faute de réponse du prestataire.');

        expect($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee)
            ->and($reservation->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
        Event::assertNothingDispatched();
    });

    it('utilise le délai configuré', function () {
        config(['sopi.reservations.delai_reponse_heures' => 1]);
        $reservation = reservationAgee(StatutReservation::EnAttente, 61);

        $this->artisan('sopi:reservations:expirer-sans-reponse')->assertSuccessful();
        expect($reservation->fresh()->statut)->toBe(StatutReservation::Annulee);
    });

    it('continue après un échec : les autres sont traitées, celle en échec reste intacte', function () {
        Event::fake([ReservationExpiree::class]);
        $a = reservationAgee(StatutReservation::EnAttente, 13 * 60);
        $enEchec = reservationAgee(StatutReservation::EnAttente, 13 * 60);
        $b = reservationAgee(StatutReservation::EnAttente, 13 * 60);
        // Panne APRÈS l'UPDATE de cette réservation, dans sa transaction : elle doit être annulée.
        DB::listen(function ($requete) use ($enEchec) {
            if (str_starts_with($requete->sql, 'update "reservations"') && in_array($enEchec->id, $requete->bindings, true)) {
                throw new RuntimeException('Panne simulée');
            }
        });

        $this->artisan('sopi:reservations:expirer-sans-reponse')->expectsOutput('2 réservation(s) annulée(s) faute de réponse du prestataire.');

        expect($a->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($b->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($enEchec->fresh()->statut)->toBe(StatutReservation::EnAttente)
            ->and($enEchec->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
        Event::assertDispatchedTimes(ReservationExpiree::class, 2);
    });

    it('est idempotent : la seconde exécution ne change rien', function () {
        Event::fake([ReservationExpiree::class]);
        reservationAgee(StatutReservation::EnAttente, 13 * 60);

        $this->artisan('sopi:reservations:expirer-sans-reponse')->expectsOutput('1 réservation(s) annulée(s) faute de réponse du prestataire.');
        $this->artisan('sopi:reservations:expirer-sans-reponse')->expectsOutput('0 réservation(s) annulée(s) faute de réponse du prestataire.');

        Event::assertDispatchedTimes(ReservationExpiree::class, 1);
    });

    it('traite plus d\'un lot', function () {
        foreach (range(1, 105) as $i) {
            reservationAgee(StatutReservation::EnAttente, 13 * 60);
        }

        $this->artisan('sopi:reservations:expirer-sans-reponse')->expectsOutput('105 réservation(s) annulée(s) faute de réponse du prestataire.');
        expect(Reservation::where('statut', StatutReservation::EnAttente)->count())->toBe(0);
    });
});

describe('b) réservations acceptées non payées (2 h depuis acceptee_le)', function () {
    it('annule à 2 h 01, libère le créneau et publie l\'événement ; laisse intacte à 1 h 59', function () {
        Event::fake([ReservationExpiree::class]);
        $impayee = reservationAgee(StatutReservation::Acceptee, 121);
        $recente = reservationAgee(StatutReservation::Acceptee, 119);

        $this->artisan('sopi:reservations:expirer-impayees')->expectsOutput('1 réservation(s) annulée(s) faute de paiement.')->assertSuccessful();

        expect($impayee->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($recente->fresh()->statut)->toBe(StatutReservation::Acceptee)
            ->and($recente->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
        expect_creneau_libre($impayee->creneau);
        Event::assertDispatchedTimes(ReservationExpiree::class, 1);
        Event::assertDispatched(ReservationExpiree::class, fn ($e) => $e->reservation->is($impayee) && $e->cause === CauseExpiration::Impayee);
    });

    it('abandonne le paiement resté en attente à l\'expiration : il passe à échoué (mémoire, tableau 2.7, 3a)', function () {
        Event::fake([ReservationExpiree::class]);
        $abandonnee = reservationAgee(StatutReservation::Acceptee, 121);
        $sessionOuverte = Paiement::factory()->enAttente()->create(['reservation_id' => $abandonnee->id, 'reference_dexpay' => 'DXP-SESSION-OUVERTE']);
        $dansLeDelai = reservationAgee(StatutReservation::Acceptee, 119);
        $enCours = Paiement::factory()->enAttente()->create(['reservation_id' => $dansLeDelai->id, 'reference_dexpay' => 'DXP-DANS-LE-DELAI']);

        $this->artisan('sopi:reservations:expirer-impayees')->expectsOutput('1 réservation(s) annulée(s) faute de paiement.')->assertSuccessful();

        // Dans le délai, le client peut encore payer : son paiement reste en attente.
        expect($abandonnee->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($sessionOuverte->fresh()->statut)->toBe(StatutPaiement::Echoue)
            ->and($dansLeDelai->fresh()->statut)->toBe(StatutReservation::Acceptee)
            ->and($enCours->fresh()->statut)->toBe(StatutPaiement::EnAttente);
    });

    it('n\'abandonne que le paiement en attente de la réservation, jamais un paiement réussi ou remboursé', function () {
        $retentee = reservationAgee(StatutReservation::Acceptee, 121);
        $premierEssai = Paiement::factory()->echoue()->create(['reservation_id' => $retentee->id]);
        $deuxiemeEssai = Paiement::factory()->enAttente()->create(['reservation_id' => $retentee->id, 'reference_dexpay' => 'DXP-DEUXIEME-ESSAI']);
        $payee = reservationAgee(StatutReservation::Confirmee, 121);
        $reussi = Paiement::factory()->reussi()->create(['reservation_id' => $payee->id]);
        $annulee = reservationAgee(StatutReservation::Annulee, 121);
        $rembourse = Paiement::factory()->rembourse()->create(['reservation_id' => $annulee->id]);

        expect(Paiement::abandonnerLesEnAttenteDe($retentee->id))->toBe(1)
            ->and(Paiement::abandonnerLesEnAttenteDe($payee->id))->toBe(0)
            ->and(Paiement::abandonnerLesEnAttenteDe($annulee->id))->toBe(0)
            ->and($premierEssai->fresh()->statut)->toBe(StatutPaiement::Echoue)
            ->and($deuxiemeEssai->fresh()->statut)->toBe(StatutPaiement::Echoue)
            ->and($reussi->fresh()->statut)->toBe(StatutPaiement::Reussi)
            ->and($rembourse->fresh()->statut)->toBe(StatutPaiement::Rembourse);
    });

    it('compte le délai depuis acceptee_le, pas depuis la création', function () {
        $reservation = reservationAgee(StatutReservation::Acceptee, 10 * 60, ['acceptee_le' => now()->subMinutes(30)]);

        $this->artisan('sopi:reservations:expirer-impayees')->assertSuccessful();
        expect($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee);
    });

    it('ne touche jamais les autres statuts, même très anciens', function (StatutReservation $statut) {
        Event::fake([ReservationExpiree::class]);
        $reservation = reservationAgee($statut, 30 * 60);

        $this->artisan('sopi:reservations:expirer-impayees')->assertSuccessful();

        expect($reservation->fresh()->statut)->toBe($statut)
            ->and($reservation->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
        Event::assertNothingDispatched();
    })->with([
        StatutReservation::EnAttente, StatutReservation::Confirmee, StatutReservation::EnCours,
        StatutReservation::Terminee, StatutReservation::Refusee, StatutReservation::Annulee,
    ]);

    it('perd la course si le client a payé juste avant : rien n\'est annulé, aucun événement', function () {
        Event::fake([ReservationExpiree::class]);
        $reservation = reservationAgee(StatutReservation::Acceptee, 3 * 60);
        Reservation::retrieved(fn ($r) => DB::table('reservations')->where('id', $r->id)->update(['statut' => 'confirmee', 'code_reservation' => 'AB12CD']));

        $this->artisan('sopi:reservations:expirer-impayees')->expectsOutput('0 réservation(s) annulée(s) faute de paiement.');

        expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee)
            ->and($reservation->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
        Event::assertNothingDispatched();
    });

    it('utilise le délai configuré', function () {
        config(['sopi.reservations.delai_paiement_heures' => 5]);
        $quatreHeures = reservationAgee(StatutReservation::Acceptee, 4 * 60);
        $sixHeures = reservationAgee(StatutReservation::Acceptee, 6 * 60);

        $this->artisan('sopi:reservations:expirer-impayees')->assertSuccessful();
        expect($quatreHeures->fresh()->statut)->toBe(StatutReservation::Acceptee)
            ->and($sixHeures->fresh()->statut)->toBe(StatutReservation::Annulee);
    });

    it('continue après un échec et reste idempotent', function () {
        Event::fake([ReservationExpiree::class]);
        $a = reservationAgee(StatutReservation::Acceptee, 3 * 60);
        $enEchec = reservationAgee(StatutReservation::Acceptee, 3 * 60);
        DB::listen(function ($requete) use ($enEchec) {
            if (str_starts_with($requete->sql, 'update "reservations"') && in_array($enEchec->id, $requete->bindings, true)) {
                throw new RuntimeException('Panne simulée');
            }
        });

        $this->artisan('sopi:reservations:expirer-impayees')->expectsOutput('1 réservation(s) annulée(s) faute de paiement.');
        $this->artisan('sopi:reservations:expirer-impayees')->expectsOutput('0 réservation(s) annulée(s) faute de paiement.');

        expect($a->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($enEchec->fresh()->statut)->toBe(StatutReservation::Acceptee);
        Event::assertDispatchedTimes(ReservationExpiree::class, 1);
    });
});

describe('c) verrous de créneaux expirés', function () {
    it('libère le verrou expiré, laisse intact le verrou valide, sans événement', function () {
        Event::fake([ReservationExpiree::class]);
        $expire = creneauVerrouleExpirant(-1);
        $valide = creneauVerrouleExpirant(3);

        $this->artisan('sopi:creneaux:liberer-verrous')->expectsOutput('1 créneau(x) libéré(s).')->assertSuccessful();

        expect_creneau_libre($expire);
        expect($valide->fresh()->statut)->toBe(StatutCreneau::Verrouille)->and($valide->fresh()->verrouille_par)->not->toBeNull();
        Event::assertNothingDispatched();
    });

    it('ne touche jamais un créneau RESERVE, INDISPONIBLE ou LIBRE', function () {
        $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
        $reserve = Creneau::factory()->for($prestataire)->reserve()->create();
        $indisponible = Creneau::factory()->for($prestataire)->indisponible()->create();
        $libre = Creneau::factory()->for($prestataire)->libre()->create();
        // Un ancien verrou_expire_a résiduel ne doit rien changer à un créneau qui n'est plus VERROUILLE.
        DB::table('creneaux')->whereIn('id', [$reserve->id, $indisponible->id, $libre->id])->update(['verrou_expire_a' => now()->subHour()]);

        $this->artisan('sopi:creneaux:liberer-verrous')->expectsOutput('0 créneau(x) libéré(s).');

        expect($reserve->fresh()->statut)->toBe(StatutCreneau::Reserve)
            ->and($indisponible->fresh()->statut)->toBe(StatutCreneau::Indisponible)
            ->and($libre->fresh()->statut)->toBe(StatutCreneau::Libre);
    });

    it('rend le créneau réservable par un autre client, et est idempotent', function () {
        $creneau = creneauVerrouleExpirant(-1);

        $this->artisan('sopi:creneaux:liberer-verrous')->expectsOutput('1 créneau(x) libéré(s).');
        $this->artisan('sopi:creneaux:liberer-verrous')->expectsOutput('0 créneau(x) libéré(s).');

        expect($creneau->fresh()->verrouillerSiLibre(300, User::factory()->create()->id))->toBeTrue();
    });

    it('rend un créneau annulé automatiquement réservable par un autre client', function () {
        $reservation = reservationAgee(StatutReservation::EnAttente, 13 * 60);
        $this->artisan('sopi:reservations:expirer-sans-reponse')->assertSuccessful();

        expect($reservation->creneau->fresh()->verrouillerSiLibre(300, User::factory()->create()->id))->toBeTrue();
    });
});

describe('planification', function () {
    it('planifie les trois commandes chaque minute, sans chevauchement', function () {
        $evenements = collect(app(Schedule::class)->events());

        foreach (['sopi:reservations:expirer-sans-reponse', 'sopi:reservations:expirer-impayees', 'sopi:creneaux:liberer-verrous'] as $commande) {
            $evenement = $evenements->first(fn ($e) => str_contains($e->command, $commande));
            expect($evenement)->not->toBeNull()
                ->and($evenement->expression)->toBe('* * * * *')
                ->and($evenement->withoutOverlapping)->toBeTrue();
        }
    });
});
