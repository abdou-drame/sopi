<?php

use App\Enums\Role;
use App\Enums\StatutCreneau;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationAnnulee;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

const URL_MES_RESERVATIONS = '/api/v1/reservations';

afterEach(fn () => Carbon::setTestNow());

/** Client actif connecté à l'API. */
function clientConnecte(): User
{
    $client = User::factory()->create();
    test()->actingAs($client, 'sanctum');

    return $client;
}

/**
 * Réservation du client sur un créneau RESERVE qui commence dans $heures heures (peut être négatif ou fractionnaire).
 * $attributs écrase les valeurs de la réservation.
 */
function reservationDuClient(User $client, float|int $heures = 24, array $attributs = [], ?Prestataire $prestataire = null): Reservation
{
    $prestataire ??= Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $debut = now()->addMinutes((int) round($heures * 60));
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);

    return Reservation::factory()->create(array_merge(['client_id' => $client->id, 'creneau_id' => $creneau->id], $attributs));
}

describe('accès', function () {
    it('renvoie 401 sans jeton, puis 403 pour un prestataire et un administrateur', function () {
        $reservation = reservationDuClient(User::factory()->create());
        $routes = [['getJson', URL_MES_RESERVATIONS], ['getJson', URL_MES_RESERVATIONS.'/'.$reservation->id], ['postJson', URL_MES_RESERVATIONS.'/'.$reservation->id.'/annuler']];

        foreach ($routes as [$methode, $url]) {
            $this->$methode($url)->assertUnauthorized();
        }
        foreach ([User::factory()->prestataire()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum');
            foreach ($routes as [$methode, $url]) {
                $this->$methode($url)->assertForbidden();
            }
        }
        expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente);
    });
});

describe('liste', function () {
    it('ne montre que les réservations du client connecté, les plus récentes d\'abord', function () {
        $moi = clientConnecte();
        $ancienne = reservationDuClient($moi, 24, ['created_at' => now()->subHours(3)]);
        $recente = reservationDuClient($moi, 24, ['created_at' => now()->subHour()]);
        reservationDuClient(User::factory()->create());

        $this->getJson(URL_MES_RESERVATIONS)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $recente->id)
            ->assertJsonPath('data.1.id', $ancienne->id)
            ->assertJsonPath('meta.total', 2);
    });

    it('pagine par 20', function () {
        $moi = clientConnecte();
        foreach (range(1, 21) as $i) {
            reservationDuClient($moi);
        }

        $this->getJson(URL_MES_RESERVATIONS)->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson(URL_MES_RESERVATIONS.'?page=2')->assertOk()->assertJsonCount(1, 'data');
    });

    it('filtre par statut valide et refuse un statut inconnu', function () {
        $moi = clientConnecte();
        reservationDuClient($moi);
        $acceptee = reservationDuClient($moi, 24, ['statut' => StatutReservation::Acceptee, 'acceptee_le' => now()]);

        $this->getJson(URL_MES_RESERVATIONS.'?statut=acceptee')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $acceptee->id);
        $this->getJson(URL_MES_RESERVATIONS.'?statut=nimporte')->assertStatus(422)->assertJsonValidationErrors('statut');
    });
});

describe('détail', function () {
    it('renvoie la réservation avec son prestataire, sans donnée personnelle de celui-ci', function () {
        $moi = clientConnecte();
        $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
        $reservation = reservationDuClient($moi, 24, [], $prestataire);

        $corps = $this->getJson(URL_MES_RESERVATIONS.'/'.$reservation->id)->assertOk()
            ->assertJsonPath('id', $reservation->id)
            ->assertJsonPath('statut', 'en_attente')
            ->assertJsonPath('motifRefus', null)
            ->assertJsonPath('codeReservation', null)
            ->assertJsonPath('peutAnnuler', true)
            ->assertJsonPath('prestataire', [
                'id' => $prestataire->id,
                'nomEntreprise' => $prestataire->nom_entreprise,
                'quartier' => $prestataire->quartier,
                'adresse' => $prestataire->adresse,
            ])
            ->assertJsonStructure(['creneau' => ['id', 'dateDebut', 'dateFin'], 'prestation' => ['id', 'nom', 'dureeMinutes', 'prix']])
            ->getContent();

        $user = $prestataire->user;
        foreach ([$user->telephone, $user->email, $user->id, $user->nom, $user->prenom] as $interdit) {
            if ($interdit !== null && $interdit !== '') {
                expect($corps)->not->toContain((string) $interdit);
            }
        }
    });

    it('répond 404 pour la réservation d\'un autre client, comme pour une inconnue ou un identifiant invalide', function () {
        clientConnecte();
        $autre = reservationDuClient(User::factory()->create(), 24, ['statut' => StatutReservation::Confirmee, 'code_reservation' => 'ZX81QK']);

        foreach ([$autre->id, fake()->uuid(), 'pas-un-uuid'] as $id) {
            $reponse = $this->getJson(URL_MES_RESERVATIONS.'/'.$id)->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
            expect($reponse->getContent())->not->toContain('ZX81QK');
        }
    });

    it('montre le motif du refus et le code uniquement quand ils existent, au client propriétaire', function () {
        $moi = clientConnecte();
        $refusee = reservationDuClient($moi, 24, ['statut' => StatutReservation::Refusee, 'motif_refus' => 'Absent ce jour-là.']);
        $confirmee = reservationDuClient($moi, 24, ['statut' => StatutReservation::Confirmee, 'code_reservation' => 'ZX81QK']);

        $this->getJson(URL_MES_RESERVATIONS.'/'.$refusee->id)->assertOk()
            ->assertJsonPath('motifRefus', 'Absent ce jour-là.')->assertJsonPath('codeReservation', null);
        $this->getJson(URL_MES_RESERVATIONS.'/'.$confirmee->id)->assertOk()
            ->assertJsonPath('codeReservation', 'ZX81QK')->assertJsonPath('motifRefus', null);

        // Un autre client connecté ne voit jamais ce code, ni dans le détail ni dans sa liste.
        $this->actingAs(User::factory()->create(), 'sanctum');
        expect($this->getJson(URL_MES_RESERVATIONS.'/'.$confirmee->id)->getContent())->not->toContain('ZX81QK')
            ->and($this->getJson(URL_MES_RESERVATIONS)->getContent())->not->toContain('ZX81QK');
    });
});

describe('annulation', function () {
    it('annule une réservation de chaque statut annulable, libère le créneau et publie l\'événement', function (StatutReservation $statut) {
        Event::fake([ReservationAnnulee::class]);
        $moi = clientConnecte();
        $extra = $statut === StatutReservation::EnAttente ? [] : ['acceptee_le' => now()];
        if ($statut === StatutReservation::Confirmee) {
            $extra['code_reservation'] = 'ABC123';
        }
        $reservation = reservationDuClient($moi, 24, ['statut' => $statut] + $extra);

        $this->postJson(URL_MES_RESERVATIONS.'/'.$reservation->id.'/annuler')->assertOk()
            ->assertJsonPath('statut', 'annulee')->assertJsonPath('peutAnnuler', false);

        $creneau = $reservation->creneau->fresh();
        expect($reservation->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($creneau->statut)->toBe(StatutCreneau::Libre)
            ->and($creneau->verrou_expire_a)->toBeNull()
            ->and($creneau->verrouille_par)->toBeNull();
        Event::assertDispatched(ReservationAnnulee::class, fn ($e) => $e->reservation->is($reservation) && $e->par === Role::Client);
    })->with([StatutReservation::EnAttente, StatutReservation::Acceptee, StatutReservation::Confirmee]);

    it('refuse les statuts non annulables avec 409', function (StatutReservation $statut) {
        Event::fake([ReservationAnnulee::class]);
        $moi = clientConnecte();
        $reservation = reservationDuClient($moi, 24, ['statut' => $statut]);

        $this->postJson(URL_MES_RESERVATIONS.'/'.$reservation->id.'/annuler')->assertStatus(409)
            ->assertExactJson(['message' => 'Cette réservation ne peut plus être annulée.']);

        expect($reservation->fresh()->statut)->toBe($statut)
            ->and($reservation->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
        Event::assertNotDispatched(ReservationAnnulee::class);
    })->with([StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Refusee, StatutReservation::Annulee]);

    it('répond 409 à une seconde annulation', function () {
        $moi = clientConnecte();
        $reservation = reservationDuClient($moi);

        $this->postJson(URL_MES_RESERVATIONS.'/'.$reservation->id.'/annuler')->assertOk();
        $this->postJson(URL_MES_RESERVATIONS.'/'.$reservation->id.'/annuler')->assertStatus(409)
            ->assertExactJson(['message' => 'Cette réservation ne peut plus être annulée.']);
    });

    it('répond 404 pour la réservation d\'un autre client sans la modifier', function () {
        clientConnecte();
        $autre = reservationDuClient(User::factory()->create());

        $this->postJson(URL_MES_RESERVATIONS.'/'.$autre->id.'/annuler')->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
        expect($autre->fresh()->statut)->toBe(StatutReservation::EnAttente)
            ->and($autre->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
    });

    it('permet à un autre client de réserver le créneau après l\'annulation', function () {
        $moi = clientConnecte();
        $reservation = reservationDuClient($moi);
        $this->postJson(URL_MES_RESERVATIONS.'/'.$reservation->id.'/annuler')->assertOk();

        $autre = User::factory()->create();
        $creneau = $reservation->creneau->fresh();
        expect($creneau->verrouillerSiLibre(300, $autre->id))->toBeTrue();
        $this->actingAs($autre, 'sanctum');
        $this->postJson(URL_MES_RESERVATIONS, ['creneau_id' => $creneau->id, 'prestation_id' => $reservation->prestation_id])->assertCreated();
    });

    it('est atomique : si la libération du créneau échoue, la réservation garde son statut', function () {
        Event::fake([ReservationAnnulee::class]);
        $moi = clientConnecte();
        $reservation = reservationDuClient($moi, 24, ['statut' => StatutReservation::Acceptee, 'acceptee_le' => now()]);
        // Le créneau n'est plus réservé : la libération conditionnelle échoue après la mise à jour de la réservation.
        $reservation->creneau->forceFill(['statut' => StatutCreneau::Indisponible])->save();

        $this->postJson(URL_MES_RESERVATIONS.'/'.$reservation->id.'/annuler')->assertStatus(409);

        expect($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee);
        Event::assertNotDispatched(ReservationAnnulee::class);
    });

    it('est atomique aussi quand la libération lève une exception', function () {
        Event::fake([ReservationAnnulee::class]);
        $moi = clientConnecte();
        $reservation = reservationDuClient($moi);
        DB::listen(function ($requete) {
            if (str_starts_with($requete->sql, 'update "creneaux"')) {
                throw new RuntimeException('Panne simulée');
            }
        });

        $this->withoutExceptionHandling();
        expect(fn () => $this->postJson(URL_MES_RESERVATIONS.'/'.$reservation->id.'/annuler'))->toThrow(RuntimeException::class);

        expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente);
        Event::assertNotDispatched(ReservationAnnulee::class);
    });
});

describe('délai d\'annulation', function () {
    beforeEach(fn () => Carbon::setTestNow('2030-01-15 10:00:00'));

    it('accepte à plus de 2 h, refuse à moins de 2 h, accepte pile à 2 h (borne incluse)', function (float $heures, int $statutHttp) {
        $moi = clientConnecte();
        $reservation = reservationDuClient($moi, $heures);

        $reponse = $this->postJson(URL_MES_RESERVATIONS.'/'.$reservation->id.'/annuler')->assertStatus($statutHttp);
        if ($statutHttp === 409) {
            $reponse->assertExactJson(['message' => 'L\'annulation n\'est plus possible moins de 2 heures avant le créneau.']);
        }
        expect($reservation->fresh()->statut)->toBe($statutHttp === 200 ? StatutReservation::Annulee : StatutReservation::EnAttente);
    })->with([
        'à 2 h 01' => [2.0166666666666666, 200],
        'pile à 2 h' => [2.0, 200],
        'à 1 h 59' => [1 + 59 / 60, 409],
        'à 30 min' => [0.5, 409],
        'déjà commencé' => [-1.0, 409],
    ]);

    it('refuse sans toucher au créneau quand le créneau est passé', function () {
        $moi = clientConnecte();
        $reservation = reservationDuClient($moi, -5);

        $this->postJson(URL_MES_RESERVATIONS.'/'.$reservation->id.'/annuler')->assertStatus(409);
        expect($reservation->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
    });

    it('utilise le délai configuré', function () {
        config(['sopi.reservations.annulation_client_heures' => 6]);
        $moi = clientConnecte();
        $quatreHeures = reservationDuClient($moi, 4);
        $septHeures = reservationDuClient($moi, 7);

        $this->postJson(URL_MES_RESERVATIONS.'/'.$quatreHeures->id.'/annuler')->assertStatus(409);
        $this->postJson(URL_MES_RESERVATIONS.'/'.$septHeures->id.'/annuler')->assertOk();
    });

    it('calcule peutAnnuler avec les mêmes règles', function () {
        $moi = clientConnecte();
        $ok = reservationDuClient($moi, 24);
        $pile = reservationDuClient($moi, 2);
        $tropTard = reservationDuClient($moi, 1);
        $terminee = reservationDuClient($moi, 24, ['statut' => StatutReservation::Terminee]);

        foreach ([[$ok, true], [$pile, true], [$tropTard, false], [$terminee, false]] as [$reservation, $attendu]) {
            $this->getJson(URL_MES_RESERVATIONS.'/'.$reservation->id)->assertOk()->assertJsonPath('peutAnnuler', $attendu);
        }
    });
});

describe('limitation de débit', function () {
    it('limite l\'annulation à 30 requêtes par minute et par utilisateur', function () {
        clientConnecte();

        foreach (range(1, 30) as $i) {
            $this->postJson(URL_MES_RESERVATIONS.'/'.fake()->uuid().'/annuler')->assertNotFound();
        }
        $this->postJson(URL_MES_RESERVATIONS.'/'.fake()->uuid().'/annuler')->assertStatus(429);
    });
});
