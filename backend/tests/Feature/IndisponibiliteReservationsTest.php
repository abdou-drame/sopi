<?php

use App\Enums\CauseAnnulation;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Enums\StatutCreneau;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationAnnulee;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

const URL_INDISPO_RESERVATIONS = '/api/v1/prestataire/agenda/indisponibilites';

function prestataireAvecReservations(): Prestataire
{
    return Prestataire::factory()->for(User::factory()->prestataire()->statut(StatutCompte::Actif), 'user')->create();
}

/** Créneau d'une heure le jour +2 à $heure, dans l'état demandé (libre, verrouille, reserve, indisponible). */
function creneauDeLaPlage(Prestataire $p, string $heure, string $etat = 'libre'): Creneau
{
    $debut = now()->addDays(2)->setTimeFromTimeString($heure);

    return Creneau::factory()->{$etat}()->for($p)->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
}

/** Réservation du statut donné (état de la factory) sur un nouveau créneau RESERVE de la plage. */
function reservationDeLaPlage(Prestataire $p, string $heure, string $etat = 'enAttente', ?User $client = null): Reservation
{
    $creneau = creneauDeLaPlage($p, $heure, 'reserve');

    return Reservation::factory()->{$etat}()->create(['client_id' => ($client ?? User::factory()->create())->id, 'creneau_id' => $creneau->id]);
}

function corpsPlageDuJour(): array
{
    return ['debut' => now()->addDays(2)->format('Y-m-d').' 08:00', 'fin' => now()->addDays(2)->format('Y-m-d').' 18:00'];
}

function etatCreneau(Creneau $c): StatutCreneau
{
    return $c->fresh()->statut;
}

describe('annulation des réservations de la plage', function () {
    it('annule les réservations EN_ATTENTE, ACCEPTEE et CONFIRMEE, bloque leurs créneaux et publie l\'événement', function (string $etat) {
        Event::fake([ReservationAnnulee::class]);
        $p = prestataireAvecReservations();
        $this->actingAs($p->user, 'sanctum');
        $reservation = reservationDeLaPlage($p, '09:00', $etat);

        $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour())->assertOk()->assertExactJson([
            'creneauxBloques' => 0, 'creneauxReserves' => 1, 'creneauxVerrouilles' => 0, 'reservationsAnnulees' => 1,
        ]);

        expect($reservation->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and(etatCreneau($reservation->creneau))->toBe(StatutCreneau::Indisponible);
        Event::assertDispatchedTimes(ReservationAnnulee::class, 1);
        Event::assertDispatched(ReservationAnnulee::class, fn ($e) => $e->reservation->is($reservation)
            && $e->par === Role::Prestataire && $e->cause === CauseAnnulation::Indisponibilite);
    })->with(['enAttente', 'acceptee', 'confirmee']);

    it('traite en une fois une plage mixte (libres, verrouillés, réservés)', function () {
        Event::fake([ReservationAnnulee::class]);
        $p = prestataireAvecReservations();
        $this->actingAs($p->user, 'sanctum');
        $libre = creneauDeLaPlage($p, '08:00');
        $verrou = creneauDeLaPlage($p, '09:00', 'verrouille');
        $a = reservationDeLaPlage($p, '10:00', 'enAttente');
        $b = reservationDeLaPlage($p, '11:00', 'confirmee');
        $dejaBloque = creneauDeLaPlage($p, '12:00', 'indisponible');

        $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour())->assertOk()->assertExactJson([
            'creneauxBloques' => 1, 'creneauxReserves' => 2, 'creneauxVerrouilles' => 1, 'reservationsAnnulees' => 2,
        ]);

        expect(etatCreneau($libre))->toBe(StatutCreneau::Indisponible)
            ->and(etatCreneau($verrou))->toBe(StatutCreneau::Indisponible)
            ->and($verrou->fresh()->verrouille_par)->toBeNull()
            ->and($verrou->fresh()->verrou_expire_a)->toBeNull()
            ->and($a->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($b->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and(etatCreneau($a->creneau))->toBe(StatutCreneau::Indisponible)
            ->and(etatCreneau($b->creneau))->toBe(StatutCreneau::Indisponible)
            ->and(etatCreneau($dejaBloque))->toBe(StatutCreneau::Indisponible);
        Event::assertDispatchedTimes(ReservationAnnulee::class, 2);
    });

    it('ne change rien au comportement pour une plage de créneaux libres seulement', function () {
        Event::fake([ReservationAnnulee::class]);
        $p = prestataireAvecReservations();
        $this->actingAs($p->user, 'sanctum');
        creneauDeLaPlage($p, '09:00');
        creneauDeLaPlage($p, '10:00');

        $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour())->assertOk()->assertJsonPath('creneauxBloques', 2)->assertJsonPath('reservationsAnnulees', 0);
        Event::assertNothingDispatched();
    });

    it('est idempotent', function () {
        Event::fake([ReservationAnnulee::class]);
        $p = prestataireAvecReservations();
        $this->actingAs($p->user, 'sanctum');
        reservationDeLaPlage($p, '09:00', 'acceptee');

        $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour())->assertOk()->assertJsonPath('reservationsAnnulees', 1);
        $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour())->assertOk()->assertJsonPath('reservationsAnnulees', 0)->assertJsonPath('creneauxBloques', 0);
        Event::assertDispatchedTimes(ReservationAnnulee::class, 1);
    });

    it('ne bloque pas pour une réservation TERMINEE, REFUSEE ou ANNULEE, dont le créneau est laissé tel quel', function (string $etat, StatutReservation $statut) {
        Event::fake([ReservationAnnulee::class]);
        $p = prestataireAvecReservations();
        $this->actingAs($p->user, 'sanctum');
        $reservation = reservationDeLaPlage($p, '09:00', $etat);
        $libre = creneauDeLaPlage($p, '10:00');

        $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour())->assertOk()->assertJsonPath('reservationsAnnulees', 0);

        expect(etatCreneau($libre))->toBe(StatutCreneau::Indisponible)
            ->and($reservation->fresh()->statut)->toBe($statut)
            ->and(etatCreneau($reservation->creneau))->toBe(StatutCreneau::Reserve);
        Event::assertNothingDispatched();
    })->with([
        'terminée' => ['terminee', StatutReservation::Terminee],
        'refusée' => ['refusee', StatutReservation::Refusee],
        'annulée' => ['annulee', StatutReservation::Annulee],
    ]);
});

describe('refus et atomicité', function () {
    it('refuse toute l\'opération (409) si une prestation est en cours, sans rien modifier', function () {
        Event::fake([ReservationAnnulee::class]);
        $p = prestataireAvecReservations();
        $this->actingAs($p->user, 'sanctum');
        $libre = creneauDeLaPlage($p, '08:00');
        $verrou = creneauDeLaPlage($p, '09:00', 'verrouille');
        $attente = reservationDeLaPlage($p, '10:00', 'enAttente');
        $enCours = reservationDeLaPlage($p, '11:00', 'enCours');

        $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour())->assertStatus(409)
            ->assertJsonPath('message', 'Une prestation est en cours dans cette plage : l\'indisponibilité ne peut pas être déclarée.');

        expect(etatCreneau($libre))->toBe(StatutCreneau::Libre)
            ->and(etatCreneau($verrou))->toBe(StatutCreneau::Verrouille)
            ->and($verrou->fresh()->verrou_expire_a)->not->toBeNull()
            ->and($attente->fresh()->statut)->toBe(StatutReservation::EnAttente)
            ->and(etatCreneau($attente->creneau))->toBe(StatutCreneau::Reserve)
            ->and($enCours->fresh()->statut)->toBe(StatutReservation::EnCours);
        Event::assertNothingDispatched();
    });

    it('annule tout en cas d\'échec au milieu : aucune réservation annulée, aucun créneau modifié, aucun événement', function () {
        Event::fake([ReservationAnnulee::class]);
        $this->withoutExceptionHandling();
        $p = prestataireAvecReservations();
        $this->actingAs($p->user, 'sanctum');
        $libre = creneauDeLaPlage($p, '08:00');
        $a = reservationDeLaPlage($p, '09:00', 'enAttente');
        $b = reservationDeLaPlage($p, '10:00', 'acceptee');
        // Panne après l'annulation de la première réservation, au moment de bloquer son créneau.
        DB::listen(function ($requete) {
            if (str_starts_with($requete->sql, 'update "creneaux"') && in_array('indisponible', $requete->bindings, true)) {
                throw new RuntimeException('Panne simulée');
            }
        });

        expect(fn () => $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour()))->toThrow(RuntimeException::class);

        expect(etatCreneau($libre))->toBe(StatutCreneau::Libre)
            ->and($a->fresh()->statut)->toBe(StatutReservation::EnAttente)
            ->and($b->fresh()->statut)->toBe(StatutReservation::Acceptee)
            ->and(etatCreneau($a->creneau))->toBe(StatutCreneau::Reserve)
            ->and(etatCreneau($b->creneau))->toBe(StatutCreneau::Reserve);
        Event::assertNothingDispatched();
    });

    it('répond 409 proprement si le statut d\'une réservation change juste avant, sans rien modifier', function () {
        Event::fake([ReservationAnnulee::class]);
        $p = prestataireAvecReservations();
        $this->actingAs($p->user, 'sanctum');
        $libre = creneauDeLaPlage($p, '08:00');
        $a = reservationDeLaPlage($p, '09:00', 'enAttente');
        $b = reservationDeLaPlage($p, '10:00', 'confirmee');
        // Entre la lecture des réservations et l'UPDATE conditionnel, la seconde passe à EN_COURS (écriture faite dans la
        // transaction du test, donc annulée avec elle : on vérifie qu'elle n'est pas annulée et que rien d'autre ne bouge).
        Reservation::retrieved(function ($r) use ($b) {
            if ($r->id === $b->id && $r->statut === StatutReservation::Confirmee) {
                DB::table('reservations')->where('id', $b->id)->update(['statut' => 'en_cours']);
            }
        });

        $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour())->assertStatus(409);

        expect(etatCreneau($libre))->toBe(StatutCreneau::Libre)
            ->and($a->fresh()->statut)->toBe(StatutReservation::EnAttente)
            ->and(etatCreneau($a->creneau))->toBe(StatutCreneau::Reserve)
            ->and($b->fresh()->statut)->not->toBe(StatutReservation::Annulee);
        Event::assertNothingDispatched();
    });
});

describe('périmètre et confidentialité', function () {
    it('ne touche jamais les créneaux ni les réservations d\'un autre prestataire', function () {
        Event::fake([ReservationAnnulee::class]);
        $autre = prestataireAvecReservations();
        $reservationAutre = reservationDeLaPlage($autre, '09:00', 'confirmee');
        $libreAutre = creneauDeLaPlage($autre, '10:00');
        $p = prestataireAvecReservations();
        $this->actingAs($p->user, 'sanctum');

        $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour())->assertOk()->assertJsonPath('reservationsAnnulees', 0);

        expect($reservationAutre->fresh()->statut)->toBe(StatutReservation::Confirmee)
            ->and(etatCreneau($reservationAutre->creneau))->toBe(StatutCreneau::Reserve)
            ->and(etatCreneau($libreAutre))->toBe(StatutCreneau::Libre);
        Event::assertNothingDispatched();
    });

    it('reste réservé aux prestataires', function () {
        foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum');
            $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour())->assertForbidden();
        }
    });

    it('montre au client sa réservation ANNULEE et ne renvoie aucune donnée personnelle au prestataire', function () {
        $p = prestataireAvecReservations();
        $client = User::factory()->create(['email' => 'client.secret@example.test']);
        $reservation = reservationDeLaPlage($p, '09:00', 'confirmee', $client);

        $this->actingAs($p->user, 'sanctum');
        $reponse = $this->postJson(URL_INDISPO_RESERVATIONS, corpsPlageDuJour())->assertOk();
        expect(array_keys($reponse->json()))->toEqualCanonicalizing(['creneauxBloques', 'creneauxReserves', 'creneauxVerrouilles', 'reservationsAnnulees'])
            ->and($reponse->getContent())->not->toContain('client.secret')->not->toContain($client->id);

        $this->actingAs($client, 'sanctum');
        $this->getJson('/api/v1/reservations')->assertOk()
            ->assertJsonFragment(['id' => $reservation->id, 'statut' => 'annulee']);
    });
});
