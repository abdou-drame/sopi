<?php

use App\Enums\StatutCompte;
use App\Enums\StatutCreneau;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationCreee;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

const URL_RESERVATIONS = '/api/v1/reservations';

/**
 * Prestataire avec un créneau à venir et une prestation active. Le créneau est verrouillé par $client si fourni
 * (expirant dans $minutes ; négatif = déjà expiré).
 *
 * @return array{creneau: Creneau, prestation: Prestation, prestataire: Prestataire}
 */
function scenarioReservation(?User $client = null, int $minutes = 5, StatutCompte $statut = StatutCompte::Actif, int $jours = 2): array
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire()->statut($statut), 'user')->create();
    $debut = now()->addDays($jours)->startOfHour();
    $creneau = Creneau::factory()->for($prestataire)->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
    if ($client !== null) {
        $creneau->forceFill(['statut' => StatutCreneau::Verrouille, 'verrouille_par' => $client->id, 'verrou_expire_a' => now()->addMinutes($minutes)])->save();
    }

    return ['creneau' => $creneau->fresh(), 'prestation' => Prestation::factory()->for($prestataire)->create(), 'prestataire' => $prestataire];
}

function corpsReservation(array $s): array
{
    return ['creneau_id' => $s['creneau']->id, 'prestation_id' => $s['prestation']->id];
}

describe('accès', function () {
    it('renvoie 401 sans jeton, puis 403 pour un prestataire et un administrateur', function () {
        $s = scenarioReservation();
        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertUnauthorized();

        foreach ([User::factory()->prestataire()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum');
            $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertForbidden();
        }
        expect(Reservation::count())->toBe(0)->and($s['creneau']->fresh()->statut)->toBe(StatutCreneau::Libre);
    });

    it('limite à 20 requêtes par minute et par utilisateur', function () {
        $this->actingAs(User::factory()->create(), 'sanctum');

        foreach (range(1, 20) as $i) {
            $this->postJson(URL_RESERVATIONS, ['creneau_id' => fake()->uuid(), 'prestation_id' => fake()->uuid()])->assertNotFound();
        }
        $this->postJson(URL_RESERVATIONS, ['creneau_id' => fake()->uuid(), 'prestation_id' => fake()->uuid()])->assertStatus(429);

        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson(URL_RESERVATIONS, ['creneau_id' => fake()->uuid(), 'prestation_id' => fake()->uuid()])->assertNotFound();
    });
});

describe('création', function () {
    it('crée la réservation en attente, réserve le créneau, vide le verrou et publie l\'événement', function () {
        Event::fake([ReservationCreee::class]);
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        $this->actingAs($client, 'sanctum');

        $reponse = $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertCreated();

        $reservation = Reservation::sole();
        expect($reservation->statut)->toBe(StatutReservation::EnAttente)
            ->and($reservation->client_id)->toBe($client->id)
            ->and($reservation->creneau_id)->toBe($s['creneau']->id)
            ->and($reservation->prestation_id)->toBe($s['prestation']->id)
            ->and($reservation->code_reservation)->toBeNull()
            ->and($reservation->acceptee_le)->toBeNull();
        $creneau = $s['creneau']->fresh();
        expect($creneau->statut)->toBe(StatutCreneau::Reserve)
            ->and($creneau->verrou_expire_a)->toBeNull()
            ->and($creneau->verrouille_par)->toBeNull();
        $reponse->assertJsonPath('id', $reservation->id)->assertJsonPath('statut', 'en_attente');
        Event::assertDispatchedTimes(ReservationCreee::class, 1);
        Event::assertDispatched(ReservationCreee::class, fn ($e) => $e->reservation->is($reservation));
    });

    it('renvoie la structure attendue', function () {
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertCreated()
            ->assertJsonStructure([
                'id', 'statut', 'dateCreation',
                'creneau' => ['id', 'dateDebut', 'dateFin'],
                'prestation' => ['id', 'nom', 'dureeMinutes', 'prix'],
                'prestataire' => ['id', 'nomEntreprise', 'quartier', 'adresse'],
            ])
            ->assertJsonPath('creneau.id', $s['creneau']->id)
            ->assertJsonPath('prestation.id', $s['prestation']->id)
            ->assertJsonPath('prestation.prix', fn ($prix) => $prix == (float) $s['prestation']->prix)
            ->assertJsonPath('prestataire.id', $s['prestataire']->id)
            ->assertJsonPath('prestataire.nomEntreprise', $s['prestataire']->nom_entreprise);
    });

    it('n\'expose aucune donnée personnelle du prestataire ni code de réservation', function () {
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        $this->actingAs($client, 'sanctum');

        $brut = $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertCreated()->getContent();
        $user = $s['prestataire']->user;

        foreach ([$user->id, $user->email, $user->telephone, $user->nom, $user->prenom, $client->id, $client->email, $client->telephone] as $interdit) {
            expect($brut)->not->toContain((string) $interdit);
        }
        expect($brut)->not->toContain('codeReservation')->not->toContain('code_reservation')->not->toContain('userId');
    });

    it('utilise le délai de verrou même quand il reste une seconde', function () {
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        $s['creneau']->forceFill(['verrou_expire_a' => now()->addSecond()])->save();
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertCreated();
    });
});

describe('verrou', function () {
    it('refuse en 409 sans verrou, créneau libre', function () {
        $s = scenarioReservation();
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertStatus(409)->assertJsonPath('message', 'Ce créneau n\'est plus disponible.');

        expect(Reservation::count())->toBe(0)->and($s['creneau']->fresh()->statut)->toBe(StatutCreneau::Libre);
    });

    it('refuse en 409 le verrou d\'un autre client', function () {
        $autre = User::factory()->create();
        $s = scenarioReservation($autre);
        $avant = $s['creneau']->only(['statut', 'verrou_expire_a', 'verrouille_par']);
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertStatus(409)->assertJsonPath('message', 'Ce créneau n\'est plus disponible.');

        expect(Reservation::count())->toBe(0)->and($s['creneau']->fresh()->only(['statut', 'verrou_expire_a', 'verrouille_par']))->toEqual($avant);
    });

    it('refuse en 409 « Délai dépassé » quand le verrou du client a expiré', function () {
        $client = User::factory()->create();
        $s = scenarioReservation($client, -1);
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Délai dépassé. Veuillez sélectionner de nouveau un créneau.');

        expect(Reservation::count())->toBe(0)->and($s['creneau']->fresh()->statut)->toBe(StatutCreneau::Verrouille);
    });

    it('refuse en 409 un verrou expiré repris par un autre client', function () {
        $client = User::factory()->create();
        $s = scenarioReservation($client, -1);
        $autre = User::factory()->create();
        expect($s['creneau']->verrouillerSiLibre(300, $autre->id))->toBeTrue();
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertStatus(409)->assertJsonPath('message', 'Ce créneau n\'est plus disponible.');

        expect($s['creneau']->fresh()->verrouille_par)->toBe($autre->id);
    });

    it('refuse en 409 un créneau déjà réservé ou indisponible', function (string $etat) {
        $s = scenarioReservation();
        $s['creneau']->forceFill(['statut' => $etat])->save();
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertStatus(409)->assertJsonPath('message', 'Ce créneau n\'est plus disponible.');

        expect(Reservation::count())->toBe(0)->and($s['creneau']->fresh()->statut->value)->toBe($etat);
    })->with(['reserve', 'indisponible']);

    it('un seul de deux clients successifs obtient le créneau', function () {
        Event::fake([ReservationCreee::class]);
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $s = scenarioReservation($a);

        $this->actingAs($a, 'sanctum');
        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertCreated();
        $this->actingAs($b, 'sanctum');
        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertStatus(409);

        expect(Reservation::count())->toBe(1)->and(Reservation::sole()->client_id)->toBe($a->id);
        Event::assertDispatchedTimes(ReservationCreee::class, 1);
    });

    it('refuse une seconde confirmation du même client', function () {
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertCreated();
        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertStatus(409);

        expect(Reservation::count())->toBe(1);
    });
});

describe('prestation et créneau', function () {
    it('refuse en 422 la prestation d\'un autre prestataire', function () {
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        $autre = Prestation::factory()->create();
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, ['creneau_id' => $s['creneau']->id, 'prestation_id' => $autre->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cette prestation n\'est pas proposée sur ce créneau.')
            ->assertJsonPath('errors.prestation_id.0', 'Cette prestation n\'est pas proposée sur ce créneau.');

        expect(Reservation::count())->toBe(0)->and($s['creneau']->fresh()->statut)->toBe(StatutCreneau::Verrouille);
    });

    it('refuse en 422 une prestation inactive ou inconnue', function (string $cas) {
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        $id = $cas === 'inactive'
            ? Prestation::factory()->inactive()->for($s['prestataire'])->create()->id
            : fake()->uuid();
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, ['creneau_id' => $s['creneau']->id, 'prestation_id' => $id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cette prestation n\'est pas proposée sur ce créneau.');

        expect(Reservation::count())->toBe(0);
    })->with(['inactive', 'inconnue']);

    it('répond 404 pour un compte prestataire non actif', function (StatutCompte $statut) {
        $client = User::factory()->create();
        $s = scenarioReservation($client, statut: $statut);
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertNotFound()->assertJsonPath('message', 'Créneau introuvable.');

        expect(Reservation::count())->toBe(0)->and($s['creneau']->fresh()->statut)->toBe(StatutCreneau::Verrouille);
    })->with([StatutCompte::EnAttenteValidation, StatutCompte::Suspendu, StatutCompte::Banni, StatutCompte::Refuse]);

    it('répond 404 pour un créneau passé', function () {
        $client = User::factory()->create();
        $s = scenarioReservation($client, jours: -1);
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertNotFound()->assertJsonPath('message', 'Créneau introuvable.');

        expect(Reservation::count())->toBe(0);
    });

    it('répond 404 pour un créneau inconnu', function () {
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, ['creneau_id' => fake()->uuid(), 'prestation_id' => $s['prestation']->id])
            ->assertNotFound()->assertJsonPath('message', 'Créneau introuvable.');
    });

    it('répond 422 pour des identifiants absents ou invalides', function (array $corps, array $champs) {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(URL_RESERVATIONS, $corps)->assertStatus(422)->assertJsonValidationErrors($champs);
    })->with([
        'vide' => [[], ['creneau_id', 'prestation_id']],
        'créneau invalide' => [['creneau_id' => 'abc', 'prestation_id' => '0198a1b2-0000-7000-8000-000000000050'], ['creneau_id']],
        'prestation invalide' => [['creneau_id' => '0198a1b2-0000-7000-8000-000000000040', 'prestation_id' => 5], ['prestation_id']],
    ]);
});

describe('atomicité', function () {
    it('annule tout si l\'insertion échoue : le créneau reste verrouillé, rien n\'est créé, aucun événement', function () {
        Event::fake([ReservationCreee::class]);
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        $this->actingAs($client, 'sanctum');
        Reservation::creating(fn () => throw new RuntimeException('Échec simulé'));

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertStatus(500);

        $creneau = $s['creneau']->fresh();
        expect(Reservation::count())->toBe(0)
            ->and($creneau->statut)->toBe(StatutCreneau::Verrouille)
            ->and($creneau->verrouille_par)->toBe($client->id)
            ->and($creneau->verrou_expire_a)->not->toBeNull();
        Event::assertNotDispatched(ReservationCreee::class);
    });

    it('répond 409 et annule tout si une réservation active existe déjà sur le créneau (index unique partiel)', function () {
        Event::fake([ReservationCreee::class]);
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        // État incohérent forcé : une réservation active occupe déjà le créneau verrouillé par le client.
        Reservation::factory()->enAttente()->create(['creneau_id' => $s['creneau']->id, 'prestation_id' => $s['prestation']->id]);
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertStatus(409)->assertJsonPath('message', 'Ce créneau n\'est plus disponible.');

        expect(Reservation::count())->toBe(1)->and($s['creneau']->fresh()->statut)->toBe(StatutCreneau::Verrouille);
        Event::assertNotDispatched(ReservationCreee::class);
    });

    it('permet une nouvelle réservation après une annulation sur le même créneau', function () {
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        Reservation::factory()->annulee()->create(['creneau_id' => $s['creneau']->id, 'prestation_id' => $s['prestation']->id]);
        $this->actingAs($client, 'sanctum');

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertCreated();

        expect(Reservation::count())->toBe(2);
    });

    it('ne fait aucune lecture avant la mise à jour qui décide du verrou', function () {
        $client = User::factory()->create();
        $s = scenarioReservation($client);
        $this->actingAs($client, 'sanctum');
        $requetes = [];
        DB::listen(function ($q) use (&$requetes) {
            $requetes[] = strtolower(ltrim($q->sql));
        });

        $this->postJson(URL_RESERVATIONS, corpsReservation($s))->assertCreated();

        $miseAJour = collect($requetes)->search(fn ($sql) => str_starts_with($sql, 'update "creneaux"') && str_contains($sql, 'verrouille_par'));
        $lecturesCreneauxApres = collect($requetes)->slice($miseAJour + 1)->filter(fn ($sql) => str_starts_with($sql, 'select') && str_contains($sql, 'from "creneaux"') && str_contains($sql, 'verrou'));
        expect($miseAJour)->not->toBeFalse()->and($lecturesCreneauxApres)->toHaveCount(0);
    });
});
