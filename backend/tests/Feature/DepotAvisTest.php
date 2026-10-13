<?php

use App\Enums\StatutReservation;
use App\Events\AvisDepose;
use App\Models\Avis;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AvisService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(fn () => Avis::flushEventListeners());

/** Réservation d'un client, au statut voulu, sur le créneau d'un prestataire. */
function reservationPourAvis(?User $client = null, StatutReservation $statut = StatutReservation::Terminee): Reservation
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create();

    return Reservation::factory()->create([
        'client_id' => ($client ?? User::factory()->create())->id,
        'creneau_id' => $creneau->id,
        'statut' => $statut->value,
    ]);
}

function urlAvis(Reservation|string $reservation): string
{
    return '/api/v1/reservations/'.($reservation instanceof Reservation ? $reservation->id : $reservation).'/avis';
}

describe('accès', function () {
    it('renvoie 401 sans jeton, puis 403 pour un prestataire et un administrateur, sans rien créer', function () {
        $reservation = reservationPourAvis();

        $this->postJson(urlAvis($reservation), ['note' => 5])->assertUnauthorized();
        foreach ([User::factory()->prestataire()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->postJson(urlAvis($reservation), ['note' => 5])->assertForbidden();
        }
        expect(Avis::count())->toBe(0);
    });
});

describe('dépôt', function () {
    it('crée l\'avis du client sur sa réservation terminée, avec le prestataire de la réservation', function () {
        Event::fake([AvisDepose::class]);
        $client = User::factory()->create();
        $reservation = reservationPourAvis($client);

        $this->actingAs($client, 'sanctum')
            ->postJson(urlAvis($reservation), ['note' => 4, 'commentaire' => '  Travail soigné et ponctuel.  '])
            ->assertCreated()
            ->assertExactJson(['note' => 4, 'commentaire' => 'Travail soigné et ponctuel.', 'affichePubliquement' => true, 'dateCreation' => Avis::first()->created_at->toIso8601String()]);

        $avis = Avis::sole();
        expect($avis->reservation_id)->toBe($reservation->id)
            ->and($avis->client_id)->toBe($client->id)
            ->and($avis->prestataire_id)->toBe($reservation->creneau->prestataire_id)
            ->and($avis->note)->toBe(4)
            ->and($avis->commentaire)->toBe('Travail soigné et ponctuel.');
        Event::assertDispatched(AvisDepose::class, fn ($e) => $e->avis->is($avis));
    });

    it('accepte une note sans commentaire, et traite un commentaire vide comme absent', function (mixed $commentaire) {
        $client = User::factory()->create();
        $reservation = reservationPourAvis($client);
        $corps = ['note' => 3] + ($commentaire === '__absent__' ? [] : ['commentaire' => $commentaire]);

        $this->actingAs($client, 'sanctum')->postJson(urlAvis($reservation), $corps)->assertCreated()->assertJsonPath('commentaire', null);

        expect(Avis::sole()->commentaire)->toBeNull();
    })->with(['__absent__', null, '', '    ']);

    it('ignore client, prestataire et réservation envoyés dans le corps', function () {
        $client = User::factory()->create();
        $reservation = reservationPourAvis($client);
        $autre = reservationPourAvis();

        $this->actingAs($client, 'sanctum')
            ->postJson(urlAvis($reservation), ['note' => 5, 'client_id' => $autre->client_id, 'prestataire_id' => $autre->creneau->prestataire_id, 'reservation_id' => $autre->id])
            ->assertCreated();

        $avis = Avis::sole();
        expect($avis->reservation_id)->toBe($reservation->id)
            ->and($avis->client_id)->toBe($client->id)
            ->and($avis->prestataire_id)->toBe($reservation->creneau->prestataire_id);
    });
});

describe('isolation', function () {
    it('répond 404 pour la réservation d\'un autre client, comme pour une réservation inconnue ou un identifiant invalide', function () {
        $reservationDeB = reservationPourAvis();
        $this->actingAs(User::factory()->create(), 'sanctum');

        foreach ([$reservationDeB->id, (string) Str::uuid(), 'pas-un-uuid'] as $id) {
            $this->postJson(urlAvis($id), ['note' => 5])->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
        }
        expect(Avis::count())->toBe(0);
    });
});

describe('statut de la réservation', function () {
    it('refuse chaque statut autre que terminée avec un message clair, sans rien créer', function (StatutReservation $statut) {
        $client = User::factory()->create();
        $reservation = reservationPourAvis($client, $statut);

        $reponse = $this->actingAs($client, 'sanctum')->postJson(urlAvis($reservation), ['note' => 5])->assertStatus(409);

        expect($reponse->json('message'))->toBeString()->not->toBe('')
            ->and(Avis::count())->toBe(0);
    })->with(array_filter(StatutReservation::cases(), fn ($s) => $s !== StatutReservation::Terminee));

    it('donne un message différent pour chaque statut refusé', function () {
        $client = User::factory()->create();
        $messages = [];
        foreach (array_filter(StatutReservation::cases(), fn ($s) => $s !== StatutReservation::Terminee) as $statut) {
            $messages[] = $this->actingAs($client, 'sanctum')->postJson(urlAvis(reservationPourAvis($client, $statut)), ['note' => 5])->json('message');
        }

        expect(array_unique($messages))->toHaveCount(6);
    });
});

describe('doublon', function () {
    it('refuse un second avis sur la même réservation et conserve le premier', function () {
        $client = User::factory()->create();
        $reservation = reservationPourAvis($client);
        $this->actingAs($client, 'sanctum')->postJson(urlAvis($reservation), ['note' => 5, 'commentaire' => 'Excellent'])->assertCreated();

        $this->postJson(urlAvis($reservation), ['note' => 1, 'commentaire' => 'Finalement non'])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Vous avez déjà donné votre avis pour cette réservation.']);

        $avis = Avis::sole();
        expect($avis->note)->toBe(5)->and($avis->commentaire)->toBe('Excellent');
    });

    it('répond le même 409 (et non une erreur 500) quand une collision d\'unicité se produit à l\'insertion', function () {
        Event::fake([AvisDepose::class]);
        $client = User::factory()->create();
        $reservation = reservationPourAvis($client);

        // Simule un dépôt concurrent : un avis apparaît entre la vérification du statut et l'insertion.
        Avis::saving(function (Avis $nouveau) use ($reservation) {
            if ($nouveau->exists) {
                return;
            }
            DB::table('avis')->insert([
                'id' => (string) Str::uuid7(),
                'reservation_id' => $reservation->id,
                'client_id' => $reservation->client_id,
                'prestataire_id' => $reservation->creneau->prestataire_id,
                'note' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->actingAs($client, 'sanctum')->postJson(urlAvis($reservation), ['note' => 5])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Vous avez déjà donné votre avis pour cette réservation.']);

        // Le faux avis concurrent est inséré dans la transaction du service : il est annulé avec elle, seul l'événement compte ici.
        Event::assertNotDispatched(AvisDepose::class);
    });

    it('lève bien une violation d\'unicité au niveau du service quand le doublon existe déjà en base', function () {
        $avis = Avis::factory()->create();

        expect(fn () => DB::table('avis')->insert([
            'id' => (string) Str::uuid7(), 'reservation_id' => $avis->reservation_id, 'client_id' => $avis->client_id,
            'prestataire_id' => $avis->prestataire_id, 'note' => 3, 'created_at' => now(), 'updated_at' => now(),
        ]))->toThrow(UniqueConstraintViolationException::class);
        expect(AvisService::dejaNote()->statut)->toBe(409);
    });
});

describe('validation', function () {
    it('accepte les notes 1 et 5 et refuse 0, 6, un nombre à virgule, un texte et l\'absence de note', function () {
        $client = User::factory()->create();
        $this->actingAs($client, 'sanctum');

        foreach ([0, 6, -1, 3.5, 'abc', null] as $note) {
            $this->postJson(urlAvis(reservationPourAvis($client)), ['note' => $note])->assertUnprocessable()->assertJsonValidationErrors('note');
        }
        $this->postJson(urlAvis(reservationPourAvis($client)))->assertUnprocessable()->assertJsonPath('errors.note.0', 'La note est obligatoire.');
        expect(Avis::count())->toBe(0);

        foreach ([1, 5] as $note) {
            $this->postJson(urlAvis(reservationPourAvis($client)), ['note' => $note])->assertCreated();
        }
        expect(Avis::count())->toBe(2);
    });

    it('donne des messages d\'erreur en français pour la note', function () {
        $client = User::factory()->create();
        $reservation = reservationPourAvis($client);

        $this->actingAs($client, 'sanctum')->postJson(urlAvis($reservation), ['note' => 6])->assertJsonPath('errors.note.0', 'La note doit être comprise entre 1 et 5.');
        $this->postJson(urlAvis($reservation), ['note' => 'abc'])->assertJsonPath('errors.note.0', 'La note doit être un nombre entier.');
    });

    it('borne le commentaire entre 3 et 1000 caractères (caractères, pas octets)', function () {
        $client = User::factory()->create();
        $this->actingAs($client, 'sanctum');

        $this->postJson(urlAvis(reservationPourAvis($client)), ['note' => 4, 'commentaire' => 'ab'])
            ->assertUnprocessable()->assertJsonPath('errors.commentaire.0', 'Le commentaire doit contenir au moins 3 caractères.');
        $this->postJson(urlAvis(reservationPourAvis($client)), ['note' => 4, 'commentaire' => str_repeat('é', 1001)])
            ->assertUnprocessable()->assertJsonPath('errors.commentaire.0', 'Le commentaire ne peut pas dépasser 1000 caractères.');
        $this->postJson(urlAvis(reservationPourAvis($client)), ['note' => 4, 'commentaire' => ['x']])->assertUnprocessable()->assertJsonValidationErrors('commentaire');
        expect(Avis::count())->toBe(0);

        $this->postJson(urlAvis(reservationPourAvis($client)), ['note' => 4, 'commentaire' => 'abc'])->assertCreated();
        $this->postJson(urlAvis(reservationPourAvis($client)), ['note' => 4, 'commentaire' => str_repeat('é', 1000)])->assertCreated();
        expect(Avis::count())->toBe(2);
    });
});

describe('limitation', function () {
    it('limite à 10 dépôts par minute et par utilisateur', function () {
        $client = User::factory()->create();
        $this->actingAs($client, 'sanctum');

        foreach (range(1, 10) as $i) {
            $this->postJson(urlAvis((string) Str::uuid()), ['note' => 5])->assertNotFound();
        }
        $this->postJson(urlAvis((string) Str::uuid()), ['note' => 5])->assertStatus(429);
    });
});

describe('réservation vue par le client', function () {
    it('indique peutNoter et l\'avis déjà donné, sans toucher aux champs existants', function () {
        $client = User::factory()->create();
        $terminee = reservationPourAvis($client);
        $this->actingAs($client, 'sanctum');

        $avant = $this->getJson('/api/v1/reservations/'.$terminee->id)->assertOk();
        $avant->assertJsonPath('peutNoter', true)->assertJsonPath('avis', null)
            ->assertJsonStructure(['id', 'statut', 'dateCreation', 'motifRefus', 'codeReservation', 'peutAnnuler', 'peutPayer', 'paiement', 'creneau', 'prestation', 'prestataire']);

        $this->postJson(urlAvis($terminee), ['note' => 4, 'commentaire' => 'Très bien'])->assertCreated();

        $this->getJson('/api/v1/reservations/'.$terminee->id)->assertOk()
            ->assertJsonPath('peutNoter', false)
            ->assertJsonPath('avis.note', 4)
            ->assertJsonPath('avis.commentaire', 'Très bien')
            ->assertJsonStructure(['avis' => ['note', 'commentaire', 'dateCreation']]);
    });

    it('n\'autorise pas à noter une réservation qui n\'est pas terminée', function () {
        $client = User::factory()->create();
        foreach (array_filter(StatutReservation::cases(), fn ($s) => $s !== StatutReservation::Terminee) as $statut) {
            $reservation = reservationPourAvis($client, $statut);
            $this->actingAs($client, 'sanctum')->getJson('/api/v1/reservations/'.$reservation->id)->assertJsonPath('peutNoter', false)->assertJsonPath('avis', null);
        }
    });

    it('liste les réservations avec leur avis sans requête supplémentaire par réservation', function () {
        $client = User::factory()->create();
        $this->actingAs($client, 'sanctum');
        $compter = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/reservations')->assertOk();

            return count(DB::getQueryLog());
        };

        foreach (range(1, 2) as $i) {
            Avis::factory()->create(['reservation_id' => reservationPourAvis($client)->id]);
        }
        $pour2 = $compter();
        foreach (range(1, 5) as $i) {
            Avis::factory()->create(['reservation_id' => reservationPourAvis($client)->id]);
            reservationPourAvis($client);
        }

        expect($compter())->toBe($pour2);
    });
});
