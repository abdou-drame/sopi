<?php

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Paiement\FakePaiementService;
use App\Services\Paiement\PaiementService;
use App\Services\Paiement\SessionPaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    Carbon::setTestNow(now());
    config(['sopi.paiement.driver' => 'fake']);
    app()->forgetInstance(PaiementService::class);
});

afterEach(fn () => Carbon::setTestNow());

function urlPayer(Reservation|string $reservation): string
{
    return '/api/v1/reservations/'.($reservation instanceof Reservation ? $reservation->id : $reservation).'/payer';
}

/** Client actif connecté à l'API. */
function clientQuiPaie(): User
{
    $client = User::factory()->create();
    test()->actingAs($client, 'sanctum');

    return $client;
}

/** Réservation ACCEPTEE du client (acceptée à l'instant sauf indication), pour une prestation au prix donné. */
function reservationAPayer(User $client, array $attributs = [], string $prix = '15000.00'): Reservation
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $prestation = Prestation::factory()->for($prestataire)->create(['prix' => $prix]);
    $debut = now()->addDay();
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);

    return Reservation::factory()->create(array_merge([
        'client_id' => $client->id,
        'creneau_id' => $creneau->id,
        'prestation_id' => $prestation->id,
        'statut' => StatutReservation::Acceptee,
        'acceptee_le' => now(),
    ], $attributs));
}

/** Remplace le prestataire de paiement par un espion du faux service ; renvoie l'espion. */
function espionDePaiement(): MockInterface
{
    $espion = Mockery::mock(FakePaiementService::class)->makePartial();
    app()->instance(PaiementService::class, $espion);

    return $espion;
}

describe('accès', function () {
    it('renvoie 401 sans jeton, puis 403 pour un prestataire et un administrateur', function () {
        $reservation = reservationAPayer(User::factory()->create());

        $this->postJson(urlPayer($reservation))->assertUnauthorized();
        foreach ([User::factory()->prestataire()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum');
            $this->postJson(urlPayer($reservation))->assertForbidden();
        }
        expect(Paiement::count())->toBe(0);
    });
});

describe('paiement nominal', function () {
    it('crée le paiement en attente avec des montants calculés par le serveur et laisse la réservation acceptée', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi, [], '15000.00');

        $reponse = $this->postJson(urlPayer($reservation))->assertCreated()
            ->assertExactJsonStructure([
                'paiement' => ['id', 'statut', 'montantAcompte', 'devise'],
                'montantRestantSurPlace',
                'urlPaiement',
                'reservation' => ['id', 'statut'],
            ])
            ->assertJsonPath('paiement.statut', 'en_attente')
            ->assertJsonPath('paiement.montantAcompte', '4500.00')
            ->assertJsonPath('paiement.devise', 'XOF')
            ->assertJsonPath('montantRestantSurPlace', '10500.00')
            ->assertJsonPath('reservation', ['id' => $reservation->id, 'statut' => 'acceptee']);

        $paiement = Paiement::sole();
        expect($paiement->reservation_id)->toBe($reservation->id)
            ->and($paiement->statut)->toBe(StatutPaiement::EnAttente)
            ->and($paiement->montant_acompte)->toBe('4500.00')
            ->and($paiement->commission)->toBe('1500.00')
            ->and($paiement->reference_dexpay)->toStartWith('FAKE-')
            ->and($paiement->url_paiement)->not->toBeNull()
            ->and($reponse->json('urlPaiement'))->toBe($paiement->url_paiement)
            ->and($reponse->json('paiement.id'))->toBe($paiement->id);

        $reservation->refresh();
        expect($reservation->statut)->toBe(StatutReservation::Acceptee)->and($reservation->code_reservation)->toBeNull();
        Http::assertNothingSent();
    });

    it('n\'expose ni la commission, ni la référence du prestataire de paiement, ni le code, ni donnée personnelle', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);

        $corps = $this->postJson(urlPayer($reservation))->assertCreated()->getContent();
        $paiement = Paiement::sole();
        $prestataire = $reservation->prestation->prestataire->user;

        expect($corps)->not->toContain('commission')->not->toContain('reference')->not->toContain('codeReservation')
            ->not->toContain('1500.00')
            ->not->toContain($prestataire->telephone)->not->toContain($prestataire->email)
            ->not->toContain($moi->telephone)->not->toContain($moi->email)
            ->and(json_decode($corps, true)['paiement'])->not->toHaveKeys(['commission', 'reference', 'referenceDexpay', 'reference_dexpay']);
        expect($paiement->commission)->toBe('1500.00');
    });

    it('suit le prix figé de la réservation, pas le prix courant de la prestation', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi, [], '15000.00');
        $reservation->prestation->update(['prix' => '20000.00']);

        $this->postJson(urlPayer($reservation))->assertCreated()
            ->assertJsonPath('paiement.montantAcompte', '4500.00')
            ->assertJsonPath('montantRestantSurPlace', '10500.00');
        expect(Paiement::sole()->commission)->toBe('1500.00');
    });

    it('appelle le prestataire de paiement hors de toute transaction ouverte par le service', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);
        $niveau = DB::transactionLevel();
        $vus = [];

        espionDePaiement()->shouldReceive('creerSession')->once()->andReturnUsing(function (...$args) use (&$vus) {
            $vus[] = DB::transactionLevel();

            return new SessionPaiement('FAKE-TEST', 'https://paiement.fake.invalid/FAKE-TEST');
        });

        $this->postJson(urlPayer($reservation))->assertCreated();

        expect($vus)->toBe([$niveau]);
    });
});

describe('idempotence', function () {
    it('renvoie le même paiement et la même adresse au deuxième appel, sans rappeler le prestataire de paiement', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);
        espionDePaiement()->shouldReceive('creerSession')->once()->passthru();

        $premier = $this->postJson(urlPayer($reservation))->assertCreated();
        $second = $this->postJson(urlPayer($reservation))->assertOk();

        expect($second->json())->toBe($premier->json())
            ->and(Paiement::count())->toBe(1);
    });

    it('répond 409 si un paiement est déjà réussi', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);
        Paiement::factory()->reussi()->create(['reservation_id' => $reservation->id]);

        $this->postJson(urlPayer($reservation))->assertStatus(409)->assertExactJson(['message' => 'Cette réservation est déjà payée.']);
        expect(Paiement::count())->toBe(1);
    });

    it('répond 409 tant qu\'un paiement récent n\'a pas encore sa page de paiement', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);
        $enCours = Paiement::factory()->create(['reservation_id' => $reservation->id]);
        espionDePaiement()->shouldNotReceive('creerSession');

        $this->postJson(urlPayer($reservation))->assertStatus(409)
            ->assertExactJson(['message' => 'Un paiement est déjà en cours d\'initialisation. Veuillez réessayer dans un instant.']);
        expect(Paiement::count())->toBe(1)->and($enCours->fresh()->statut)->toBe(StatutPaiement::EnAttente);
    });

    it('reprend un paiement abandonné sans référence : il passe à échoué et un nouveau est créé', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);
        $abandonne = Paiement::factory()->create(['reservation_id' => $reservation->id, 'created_at' => now()->subMinutes(3)]);

        $this->postJson(urlPayer($reservation))->assertCreated();

        expect($abandonne->fresh()->statut)->toBe(StatutPaiement::Echoue)
            ->and(Paiement::count())->toBe(2)
            ->and($reservation->paiementActif->id)->not->toBe($abandonne->id);
    });

    it('renvoie le paiement existant quand l\'index unique partiel refuse l\'insertion', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);
        $concurrent = (string) Str::uuid();
        $fait = false;

        // Un autre processus insère un paiement actif juste avant notre insertion (après la lecture des paiements actifs).
        DB::listen(function ($requete) use (&$fait, $reservation, $concurrent) {
            if ($fait || ! str_starts_with($requete->sql, 'select') || ! str_contains($requete->sql, 'from "paiements"')) {
                return;
            }
            $fait = true;
            DB::table('paiements')->insert([
                'id' => $concurrent, 'reservation_id' => $reservation->id, 'montant_acompte' => '4500.00', 'commission' => '1500.00',
                'statut' => 'en_attente', 'reference_dexpay' => 'FAKE-CONCURRENT', 'url_paiement' => 'https://paiement.fake.invalid/FAKE-CONCURRENT',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        espionDePaiement()->shouldNotReceive('creerSession');

        $this->postJson(urlPayer($reservation))->assertOk()
            ->assertJsonPath('paiement.id', $concurrent)
            ->assertJsonPath('urlPaiement', 'https://paiement.fake.invalid/FAKE-CONCURRENT');
        expect($fait)->toBeTrue()->and(Paiement::count())->toBe(1);
    });
});

describe('règles sur la réservation', function () {
    it('refuse une réservation qui n\'est pas en attente de paiement', function (string $etat, string $message) {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);
        $reservation->forceFill(['statut' => $etat, 'code_reservation' => in_array($etat, ['confirmee', 'en_cours', 'terminee'], true) ? 'AB12CD' : null])->save();

        $this->postJson(urlPayer($reservation))->assertStatus(409)->assertExactJson(['message' => $message]);
        expect(Paiement::count())->toBe(0)->and($reservation->fresh()->statut->value)->toBe($etat);
    })->with([
        'en_attente' => ['en_attente', 'Cette réservation n\'est pas en attente de paiement.'],
        'refusee' => ['refusee', 'Cette réservation n\'est pas en attente de paiement.'],
        'annulee' => ['annulee', 'Cette réservation n\'est pas en attente de paiement.'],
        'confirmee' => ['confirmee', 'Cette réservation est déjà payée.'],
        'en_cours' => ['en_cours', 'Cette réservation est déjà payée.'],
        'terminee' => ['terminee', 'Cette réservation est déjà payée.'],
    ]);

    it('refuse une réservation dont le délai de paiement est dépassé', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi, ['acceptee_le' => now()->subHours(3)]);

        $this->postJson(urlPayer($reservation))->assertStatus(409)->assertExactJson(['message' => 'Le délai de paiement est dépassé.']);
        expect(Paiement::count())->toBe(0);
    });

    it('respecte la borne du délai à une minute près', function () {
        $moi = clientQuiPaie();
        $avant = reservationAPayer($moi, ['acceptee_le' => now()->subHours(2)->addMinute()]);
        $apres = reservationAPayer($moi, ['acceptee_le' => now()->subHours(2)->subMinute()]);

        $this->postJson(urlPayer($avant))->assertCreated();
        $this->postJson(urlPayer($apres))->assertStatus(409)->assertJsonPath('message', 'Le délai de paiement est dépassé.');
        expect(Paiement::count())->toBe(1);
    });

    it('répond 404 pour la réservation d\'un autre client, une inconnue et un identifiant mal formé', function () {
        clientQuiPaie();
        $autre = reservationAPayer(User::factory()->create());

        foreach ([$autre->id, fake()->uuid(), 'pas-un-uuid'] as $id) {
            $this->postJson(urlPayer($id))->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
        }
        expect(Paiement::count())->toBe(0);
    });

    it('répond 409 proprement et ne crée rien si la réservation est annulée juste avant le verrou', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);
        $fait = false;
        Reservation::retrieved(function (Reservation $lue) use (&$fait, $reservation) {
            if (! $fait && $lue->id === $reservation->id) {
                $fait = true;
                DB::table('reservations')->where('id', $lue->id)->update(['statut' => 'annulee']);
            }
        });
        espionDePaiement()->shouldNotReceive('creerSession');

        $this->postJson(urlPayer($reservation))->assertStatus(409)
            ->assertExactJson(['message' => 'Cette réservation n\'est pas en attente de paiement.']);
        expect($fait)->toBeTrue()->and(Paiement::count())->toBe(0)->and($reservation->fresh()->statut)->toBe(StatutReservation::Annulee);
    });
});

describe('échec du prestataire de paiement', function () {
    it('passe le paiement à échoué, répond 503, journalise sans détail sensible et permet de recommencer', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);
        $espion = espionDePaiement();
        $espion->shouldReceive('creerSession')->once()->andThrow(new RuntimeException('clé secrète sk_live_123 refusée'));
        Log::shouldReceive('error')->once()->withArgs(function (string $message, array $contexte) {
            return $contexte['evenement'] === 'dexpay.creer_session_echec' && isset($contexte['paiement_id']) && $contexte['exception'] === RuntimeException::class
                && ! str_contains(json_encode($contexte), 'sk_live');
        });

        $this->postJson(urlPayer($reservation))->assertStatus(503)
            ->assertExactJson(['message' => 'Le service de paiement est momentanément indisponible. Veuillez réessayer.']);

        $echoue = Paiement::sole();
        expect($echoue->statut)->toBe(StatutPaiement::Echoue)
            ->and($echoue->reference_dexpay)->toBeNull()
            ->and($reservation->paiementActif)->toBeNull()
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee);

        // Le prestataire de paiement répond de nouveau : un nouveau paiement est créé.
        app()->instance(PaiementService::class, new FakePaiementService);
        $this->postJson(urlPayer($reservation))->assertCreated();

        expect(Paiement::count())->toBe(2)
            ->and(Paiement::where('statut', 'echoue')->count())->toBe(1)
            ->and(Paiement::where('statut', 'en_attente')->count())->toBe(1);
    });

    it('traite une session incomplète comme un échec', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);
        espionDePaiement()->shouldReceive('creerSession')->once()->andReturn(new SessionPaiement('', ''));

        $this->postJson(urlPayer($reservation))->assertStatus(503);
        expect(Paiement::sole()->statut)->toBe(StatutPaiement::Echoue);
    });

    it('répond 503 quand le pilote de paiement est mal configuré', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);
        config(['sopi.paiement.driver' => 'dexpay']);
        app()->forgetInstance(PaiementService::class);

        $this->postJson(urlPayer($reservation))->assertStatus(503);
        expect(Paiement::sole()->statut)->toBe(StatutPaiement::Echoue);
    });
});

describe('peutPayer', function () {
    it('est vrai seulement pour une réservation acceptée, dans le délai et sans paiement réussi', function () {
        $moi = clientQuiPaie();
        $payable = reservationAPayer($moi);
        $tardive = reservationAPayer($moi, ['acceptee_le' => now()->subHours(3)]);
        $enAttente = reservationAPayer($moi, ['statut' => StatutReservation::EnAttente, 'acceptee_le' => null]);
        $confirmee = reservationAPayer($moi, ['statut' => StatutReservation::Confirmee, 'code_reservation' => 'AB12CD']);
        $payee = reservationAPayer($moi);
        Paiement::factory()->reussi()->create(['reservation_id' => $payee->id]);
        $enCours = reservationAPayer($moi);
        Paiement::factory()->create(['reservation_id' => $enCours->id]);

        $attendu = [$payable->id => true, $tardive->id => false, $enAttente->id => false, $confirmee->id => false, $payee->id => false, $enCours->id => true];

        $liste = collect($this->getJson('/api/v1/reservations')->assertOk()->json('data'))->pluck('peutPayer', 'id')->all();
        expect($liste)->toEqual($attendu);
        foreach ($attendu as $id => $valeur) {
            $this->getJson('/api/v1/reservations/'.$id)->assertOk()->assertJsonPath('peutPayer', $valeur);
        }
    });

    it('devient faux après l\'annulation', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);

        $this->postJson('/api/v1/reservations/'.$reservation->id.'/annuler')->assertOk()->assertJsonPath('peutPayer', false);
    });
});

describe('limitation de débit', function () {
    it('répond 429 après 10 appels par minute', function () {
        $moi = clientQuiPaie();
        $reservation = reservationAPayer($moi);

        foreach (range(1, 10) as $i) {
            $this->postJson(urlPayer($reservation))->assertSuccessful();
        }
        $this->postJson(urlPayer($reservation))->assertStatus(429);
        expect(Paiement::count())->toBe(1);
    });
});
