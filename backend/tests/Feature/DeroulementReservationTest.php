<?php

use App\Enums\StatutCreneau;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationDemarree;
use App\Events\Reservation\ReservationTerminee;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

const URL_PRESTATIONS_EN_COURS = '/api/v1/prestataire/reservations';

/** Prestataire actif connecté à l'API. */
function prestataireEnService(): Prestataire
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    test()->actingAs($prestataire->user, 'sanctum');

    return $prestataire;
}

/** Réservation d'un créneau RESERVE du prestataire, au statut demandé, avec un code posé directement (le paiement vient en M6). */
function reservationAuStatut(Prestataire $prestataire, StatutReservation $statut = StatutReservation::Confirmee, string $code = 'AB12CD'): Reservation
{
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create();

    return Reservation::factory()->create([
        'client_id' => User::factory()->create(['prenom' => 'Awa', 'nom' => 'diop'])->id,
        'creneau_id' => $creneau->id,
        'statut' => $statut,
        'code_reservation' => $code,
        'acceptee_le' => now(),
    ]);
}

function urlAction(Reservation|string $reservation, string $action): string
{
    return URL_PRESTATIONS_EN_COURS.'/'.($reservation instanceof Reservation ? $reservation->id : $reservation).'/'.$action;
}

describe('accès', function () {
    it('renvoie 401 sans jeton, puis 403 pour un client et un administrateur', function () {
        $reservation = reservationAuStatut(Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create());

        $this->postJson(urlAction($reservation, 'demarrer'), ['code' => 'AB12CD'])->assertUnauthorized();
        $this->postJson(urlAction($reservation, 'terminer'))->assertUnauthorized();
        foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum');
            $this->postJson(urlAction($reservation, 'demarrer'), ['code' => 'AB12CD'])->assertForbidden();
            $this->postJson(urlAction($reservation, 'terminer'))->assertForbidden();
        }
        expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
    });
});

describe('démarrer', function () {
    it('passe la réservation à EN_COURS avec le bon code et publie l\'événement', function () {
        Event::fake([ReservationDemarree::class]);
        $reservation = reservationAuStatut(prestataireEnService());

        $this->postJson(urlAction($reservation, 'demarrer'), ['code' => 'AB12CD'])->assertOk()->assertJsonPath('statut', 'en_cours');

        expect($reservation->fresh()->statut)->toBe(StatutReservation::EnCours);
        Event::assertDispatched(ReservationDemarree::class, fn ($e) => $e->reservation->is($reservation));
    });

    it('accepte le code en minuscules et avec des espaces autour', function (string $saisie) {
        $reservation = reservationAuStatut(prestataireEnService());

        $this->postJson(urlAction($reservation, 'demarrer'), ['code' => $saisie])->assertOk();
        expect($reservation->fresh()->statut)->toBe(StatutReservation::EnCours);
    })->with(['minuscules' => 'ab12cd', 'espaces' => '  AB12CD ', 'mixte' => ' aB12cD  ']);

    it('refuse un code incorrect avec 422, sans changer le statut ni publier d\'événement', function () {
        Event::fake([ReservationDemarree::class]);
        $reservation = reservationAuStatut(prestataireEnService());

        $reponse = $this->postJson(urlAction($reservation, 'demarrer'), ['code' => 'ZZ99ZZ'])->assertStatus(422)
            ->assertJsonPath('message', 'Code de réservation incorrect.')
            ->assertJsonPath('errors.code.0', 'Code de réservation incorrect.');

        expect($reponse->getContent())->not->toContain('AB12CD')
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
        Event::assertNotDispatched(ReservationDemarree::class);
    });

    it('refuse un code mal formé ou absent avec 422', function (array $corps) {
        $reservation = reservationAuStatut(prestataireEnService());

        $this->postJson(urlAction($reservation, 'demarrer'), $corps)->assertStatus(422)->assertJsonValidationErrors('code');
        expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
    })->with([
        'absent' => [[]],
        'trop court' => [['code' => 'AB12']],
        'trop long' => [['code' => 'AB12CDE']],
        'caractères interdits' => [['code' => 'AB-2CD']],
        'tableau' => [['code' => ['AB12CD']]],
    ]);

    it('répond 409 pour chaque statut autre que CONFIRMEE, même avec le bon code', function (StatutReservation $statut) {
        Event::fake([ReservationDemarree::class]);
        $reservation = reservationAuStatut(prestataireEnService(), $statut);

        $this->postJson(urlAction($reservation, 'demarrer'), ['code' => 'AB12CD'])->assertStatus(409)
            ->assertExactJson(['message' => 'Cette réservation ne peut pas être démarrée.']);

        expect($reservation->fresh()->statut)->toBe($statut);
        Event::assertNotDispatched(ReservationDemarree::class);
    })->with([
        StatutReservation::EnAttente, StatutReservation::Acceptee, StatutReservation::EnCours,
        StatutReservation::Terminee, StatutReservation::Refusee, StatutReservation::Annulee,
    ]);

    it('répond 409 à un double démarrage', function () {
        $reservation = reservationAuStatut(prestataireEnService());

        $this->postJson(urlAction($reservation, 'demarrer'), ['code' => 'AB12CD'])->assertOk();
        $this->postJson(urlAction($reservation, 'demarrer'), ['code' => 'AB12CD'])->assertStatus(409);
    });

    it('est atomique : si la mise à jour échoue, le statut reste inchangé et aucun événement n\'est envoyé', function () {
        Event::fake([ReservationDemarree::class]);
        $reservation = reservationAuStatut(prestataireEnService());
        // La panne survient APRÈS l'exécution de l'UPDATE, dans la transaction : elle doit être annulée.
        DB::listen(function ($requete) {
            if (str_starts_with($requete->sql, 'update "reservations"')) {
                throw new RuntimeException('Panne simulée');
            }
        });

        $this->withoutExceptionHandling();
        expect(fn () => $this->postJson(urlAction($reservation, 'demarrer'), ['code' => 'AB12CD']))->toThrow(RuntimeException::class);

        expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
        Event::assertNotDispatched(ReservationDemarree::class);
    });
});

describe('terminer', function () {
    it('passe la réservation à TERMINEE, garde le créneau réservé et publie l\'événement', function () {
        Event::fake([ReservationTerminee::class]);
        $reservation = reservationAuStatut(prestataireEnService(), StatutReservation::EnCours);

        $this->postJson(urlAction($reservation, 'terminer'))->assertOk()->assertJsonPath('statut', 'terminee');

        expect($reservation->fresh()->statut)->toBe(StatutReservation::Terminee)
            ->and($reservation->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
        Event::assertDispatched(ReservationTerminee::class, fn ($e) => $e->reservation->is($reservation));
    });

    it('répond 409 pour chaque statut autre que EN_COURS', function (StatutReservation $statut) {
        Event::fake([ReservationTerminee::class]);
        $reservation = reservationAuStatut(prestataireEnService(), $statut);

        $this->postJson(urlAction($reservation, 'terminer'))->assertStatus(409)
            ->assertExactJson(['message' => 'Cette réservation ne peut pas être terminée.']);

        expect($reservation->fresh()->statut)->toBe($statut);
        Event::assertNotDispatched(ReservationTerminee::class);
    })->with([
        StatutReservation::EnAttente, StatutReservation::Acceptee, StatutReservation::Confirmee,
        StatutReservation::Terminee, StatutReservation::Refusee, StatutReservation::Annulee,
    ]);

    it('est atomique : si la mise à jour échoue, le statut reste inchangé et aucun événement n\'est envoyé', function () {
        Event::fake([ReservationTerminee::class]);
        $reservation = reservationAuStatut(prestataireEnService(), StatutReservation::EnCours);
        DB::listen(function ($requete) {
            if (str_starts_with($requete->sql, 'update "reservations"')) {
                throw new RuntimeException('Panne simulée');
            }
        });

        $this->withoutExceptionHandling();
        expect(fn () => $this->postJson(urlAction($reservation, 'terminer')))->toThrow(RuntimeException::class);

        expect($reservation->fresh()->statut)->toBe(StatutReservation::EnCours);
        Event::assertNotDispatched(ReservationTerminee::class);
    });
});

describe('propriété et confidentialité', function () {
    it('répond 404 pour la réservation d\'un autre prestataire, inconnue ou à identifiant invalide', function () {
        prestataireEnService();
        $autre = reservationAuStatut(Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create(), StatutReservation::Confirmee, 'QQ77WW');
        $enCours = reservationAuStatut(Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create(), StatutReservation::EnCours);

        foreach ([$autre->id, fake()->uuid(), 'pas-un-uuid'] as $id) {
            $this->postJson(urlAction($id, 'demarrer'), ['code' => 'QQ77WW'])->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
        }
        $this->postJson(urlAction($enCours, 'terminer'))->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
        expect($autre->fresh()->statut)->toBe(StatutReservation::Confirmee)
            ->and($enCours->fresh()->statut)->toBe(StatutReservation::EnCours);
    });

    it('n\'expose ni code de réservation ni donnée personnelle du client', function () {
        $moi = prestataireEnService();
        $reservation = reservationAuStatut($moi);
        $client = $reservation->client;

        $demarree = $this->postJson(urlAction($reservation, 'demarrer'), ['code' => 'AB12CD'])->assertOk()
            ->assertJsonPath('client', ['prenom' => 'Awa', 'initialeNom' => 'D'])->getContent();
        $terminee = $this->postJson(urlAction($reservation, 'terminer'))->assertOk()->getContent();

        foreach ([$demarree, $terminee] as $corps) {
            foreach (['AB12CD', 'code_reservation', 'codeReservation', $client->telephone, $client->email, $client->id, 'Diop'] as $interdit) {
                if ($interdit !== null && $interdit !== '') {
                    expect($corps)->not->toContain((string) $interdit);
                }
            }
        }
    });
});

describe('protection contre la devinette du code', function () {
    it('bloque le 6e essai sur une réservation sans bloquer une autre', function () {
        $moi = prestataireEnService();
        $cible = reservationAuStatut($moi);
        $autre = reservationAuStatut($moi, StatutReservation::Confirmee, 'EF34GH');

        foreach (range(1, 5) as $i) {
            $this->postJson(urlAction($cible, 'demarrer'), ['code' => 'ZZ99ZZ'])->assertStatus(422);
        }
        // Le 6e essai est bloqué, même avec le bon code.
        $this->postJson(urlAction($cible, 'demarrer'), ['code' => 'AB12CD'])->assertStatus(429)
            ->assertJsonPath('message', 'Trop de tentatives. Réessayez dans quelques instants.');
        expect($cible->fresh()->statut)->toBe(StatutReservation::Confirmee);

        $this->postJson(urlAction($autre, 'demarrer'), ['code' => 'EF34GH'])->assertOk();
    });

    it('compte aussi les codes mal formés', function () {
        $reservation = reservationAuStatut(prestataireEnService());

        foreach (range(1, 5) as $i) {
            $this->postJson(urlAction($reservation, 'demarrer'), ['code' => 'x'])->assertStatus(422);
        }
        $this->postJson(urlAction($reservation, 'demarrer'), ['code' => 'AB12CD'])->assertStatus(429);
    });

    it('ne limite pas « terminer » à 5 essais', function () {
        $reservation = reservationAuStatut(prestataireEnService(), StatutReservation::Confirmee);

        foreach (range(1, 6) as $i) {
            $this->postJson(urlAction($reservation, 'terminer'))->assertStatus(409);
        }
    });
});
