<?php

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationConfirmee;
use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use App\Services\GenerateurCodeReservation;
use App\Services\Paiement\FakePaiementService;
use App\Services\Paiement\PaiementService;
use App\Services\Paiement\ResultatOperation;
use App\Services\RemboursementPaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

const SECRET_WEBHOOK_TEST = 'secret-factice-de-test';
const URL_WEBHOOK = '/api/v1/webhooks/paiement';

beforeEach(function () {
    Http::preventStrayRequests();
    Carbon::setTestNow(now());
    config(['sopi.paiement.driver' => 'fake', 'sopi.paiement.webhook_secret' => SECRET_WEBHOOK_TEST]);
    app()->forgetInstance(PaiementService::class);
});

afterEach(fn () => Carbon::setTestNow());

/** Client + prestataire + réservation ACCEPTEE (15 000 FCFA, acompte 4 500). */
function scenarioWebhook(string $prix = '15000.00'): array
{
    $client = User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diop']);
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $prestation = Prestation::factory()->for($prestataire)->create(['prix' => $prix]);
    $debut = now()->addDays(2);
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
    $reservation = Reservation::factory()->acceptee()->create([
        'client_id' => $client->id, 'creneau_id' => $creneau->id, 'prestation_id' => $prestation->id,
    ]);

    return [$client, $prestataire, $reservation];
}

/** Le client appelle POST /payer : crée le paiement EN_ATTENTE avec sa référence (faux prestataire). */
function paiementInitie(User $client, Reservation $reservation): Paiement
{
    test()->actingAs($client, 'sanctum');
    test()->postJson('/api/v1/reservations/'.$reservation->id.'/payer')->assertSuccessful();

    return Paiement::query()->where('reservation_id', $reservation->id)->latest('created_at')->latest('id')->firstOrFail();
}

function corpsWebhook(Paiement|string $reference, string $statut = 'reussi', ?string $montant = null): string
{
    return json_encode([
        'reference' => $reference instanceof Paiement ? $reference->reference_dexpay : $reference,
        'statut' => $statut,
        'montant' => $montant ?? ($reference instanceof Paiement ? $reference->montant_acompte : '4500.00'),
    ]);
}

function signerWebhook(string $corps, string $secret = SECRET_WEBHOOK_TEST): string
{
    return hash_hmac('sha256', $corps, $secret);
}

/** Envoie le corps BRUT, signé par défaut avec le secret de test ; $signature = null n'envoie aucun en-tête. */
function appelerWebhook(string $corps, ?string $signature = 'auto'): TestResponse
{
    $serveur = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
    $signature = $signature === 'auto' ? signerWebhook($corps) : $signature;
    if ($signature !== null) {
        $serveur['HTTP_X_WEBHOOK_SIGNATURE'] = $signature;
    }

    return test()->call('POST', URL_WEBHOOK, [], [], [], $serveur, $corps);
}

function espionRemboursement(): MockInterface
{
    $espion = Mockery::mock(FakePaiementService::class, [SECRET_WEBHOOK_TEST])->makePartial();
    app()->instance(PaiementService::class, $espion);

    return $espion;
}

describe('signature et corps', function () {
    it('est accessible sans jeton et confirme avec une signature valide', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);

        appelerWebhook(corpsWebhook($paiement))->assertOk()->assertJsonPath('message', 'Paiement confirmé.');
    });

    it('refuse une signature absente, fausse ou calculée avec un autre secret : 401, rien n\'est modifié', function (?string $signature) {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);

        appelerWebhook(corpsWebhook($paiement), $signature)->assertUnauthorized()->assertJsonPath('message', 'Signature invalide.');

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::EnAttente)
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee)
            ->and($reservation->fresh()->code_reservation)->toBeNull();
    })->with([
        'absente' => [null],
        'vide' => [''],
        'fausse' => [str_repeat('a', 64)],
        'autre secret' => [fn () => signerWebhook(corpsWebhook('FAKE-X'), 'autre-secret')],
    ]);

    it('refuse tout quand le secret de configuration est vide, même avec une signature calculée sur un secret vide', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        config(['sopi.paiement.webhook_secret' => '']);
        app()->forgetInstance(PaiementService::class);
        $corps = corpsWebhook($paiement);

        appelerWebhook($corps, hash_hmac('sha256', $corps, ''))->assertUnauthorized();
        appelerWebhook($corps, null)->assertUnauthorized();
        expect($paiement->fresh()->statut)->toBe(StatutPaiement::EnAttente);
    });

    it('vérifie la signature sur le corps brut : un corps recodé (espaces) ne passe pas avec l\'ancienne signature', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $corps = corpsWebhook($paiement);
        $signature = signerWebhook($corps);

        appelerWebhook(json_encode(json_decode($corps), JSON_PRETTY_PRINT), $signature)->assertUnauthorized();
        expect($paiement->fresh()->statut)->toBe(StatutPaiement::EnAttente);
    });

    it('utilise l\'en-tête de signature configurable', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        config(['sopi.paiement.signature_header' => 'X-Dexpay-Signature']);
        $corps = corpsWebhook($paiement);

        // L'ancien en-tête n'est plus lu.
        appelerWebhook($corps)->assertUnauthorized();
        test()->call('POST', URL_WEBHOOK, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_DEXPAY_SIGNATURE' => signerWebhook($corps)], $corps)->assertOk();
        expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
    });

    it('renvoie 422 pour un corps illisible ou incomplet mais bien signé', function (string $corps) {
        appelerWebhook($corps)->assertStatus(422);
    })->with([
        'pas du JSON' => ['ceci n\'est pas du json'],
        'tableau vide' => ['{}'],
        'statut inconnu' => ['{"reference":"FAKE-A","statut":"autre","montant":"4500.00"}'],
        'montant manquant' => ['{"reference":"FAKE-A","statut":"reussi"}'],
        'montant nul' => ['{"reference":"FAKE-A","statut":"reussi","montant":"0"}'],
    ]);

    it('refuse un corps de plus de 64 Ko avec 413', function () {
        $corps = str_repeat('x', 65537);

        appelerWebhook($corps)->assertStatus(413);
    });

    it('limite à 120 requêtes par minute et par adresse IP', function () {
        foreach (range(1, 120) as $i) {
            appelerWebhook('{}', null)->assertUnauthorized();
        }

        appelerWebhook('{}', null)->assertStatus(429);
    });
});

describe('contrôles avant traitement', function () {
    it('ignore une référence inconnue : 200, aucune modification, journal sans le corps', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $spy = Log::spy();

        appelerWebhook(corpsWebhook('FAKE-INCONNUE'))->assertOk()->assertJsonPath('message', 'Événement ignoré.');

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::EnAttente)->and($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee);
        $spy->shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $contexte = []) => $contexte === []
            && ! str_contains($message, 'FAKE-INCONNUE'));
    });

    it('refuse un montant différent de l\'acompte : 422, aucune modification, journal avec l\'identifiant seulement', function (string $montant) {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $spy = Log::spy();

        appelerWebhook(corpsWebhook($paiement, 'reussi', $montant))->assertStatus(422);

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::EnAttente)
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee)
            ->and($reservation->fresh()->code_reservation)->toBeNull();
        $spy->shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $contexte) => array_keys($contexte) === ['paiement_id']
            && $contexte['paiement_id'] === $paiement->id);
    })->with(['inférieur' => '4499.99', 'supérieur' => '4500.01', 'prix entier' => '15000.00']);

    it('compare les montants de façon décimale exacte (« 4500 » vaut « 4500.00 »)', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);

        appelerWebhook(corpsWebhook($paiement, 'reussi', '4500'))->assertOk();
        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Reussi);
    });
});

describe('événement réussi', function () {
    it('confirme : paiement reussi, réservation confirmee, code conforme, événement publié', function () {
        Event::fake([ReservationConfirmee::class]);
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);

        appelerWebhook(corpsWebhook($paiement))->assertOk()->assertJsonPath('message', 'Paiement confirmé.');

        $paiement->refresh();
        $reservation->refresh();
        expect($paiement->statut)->toBe(StatutPaiement::Reussi)
            ->and($paiement->paye_le)->not->toBeNull()
            ->and($reservation->statut)->toBe(StatutReservation::Confirmee)
            ->and($reservation->code_reservation)->toMatch('/^[A-Z0-9]{6}$/')
            ->and($reservation->code_reservation)->not->toMatch('/[01OIL]/');
        Event::assertDispatchedTimes(ReservationConfirmee::class, 1);
        Event::assertDispatched(ReservationConfirmee::class, fn ($e) => $e->reservation->is($reservation));
    });

    it('montre le code au client, jamais au prestataire, et peutPayer devient faux', function () {
        [$client, $prestataire, $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        appelerWebhook(corpsWebhook($paiement))->assertOk();
        $code = $reservation->fresh()->code_reservation;

        $this->actingAs($client, 'sanctum');
        $this->getJson('/api/v1/reservations/'.$reservation->id)->assertOk()
            ->assertJsonPath('statut', 'confirmee')
            ->assertJsonPath('codeReservation', $code)
            ->assertJsonPath('peutPayer', false);
        $this->getJson('/api/v1/reservations')->assertOk()->assertJsonPath('data.0.peutPayer', false);

        $this->actingAs($prestataire->user, 'sanctum');
        foreach (['/api/v1/prestataire/reservations', '/api/v1/prestataire/reservations/'.$reservation->id] as $url) {
            $reponse = $this->getJson($url)->assertOk();
            expect($reponse->getContent())->not->toContain($code)->not->toContain('codeReservation');
        }
    });

    it('est idempotent : le même webhook deux fois donne 200, un seul événement et le même code', function () {
        Event::fake([ReservationConfirmee::class]);
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $corps = corpsWebhook($paiement);

        appelerWebhook($corps)->assertOk()->assertJsonPath('message', 'Paiement confirmé.');
        $code = $reservation->fresh()->code_reservation;
        $miseAJour = $reservation->fresh()->updated_at;
        appelerWebhook($corps)->assertOk()->assertJsonPath('message', 'Déjà traité.');

        expect($reservation->fresh()->code_reservation)->toBe($code)
            ->and($reservation->fresh()->updated_at->equalTo($miseAJour))->toBeTrue()
            ->and(Paiement::count())->toBe(1);
        Event::assertDispatchedTimes(ReservationConfirmee::class, 1);
    });

    it('accepte un paiement arrivé après le délai de 2 h tant que la réservation est encore ACCEPTEE', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $reservation->forceFill(['acceptee_le' => now()->subHours(3)])->save();

        appelerWebhook(corpsWebhook($paiement))->assertOk();

        expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
    });

    it('réactive un paiement déjà échoué (session orpheline payée) et confirme la réservation', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $paiement->forceFill(['statut' => StatutPaiement::Echoue])->save();

        appelerWebhook(corpsWebhook($paiement))->assertOk()->assertJsonPath('message', 'Paiement confirmé.');

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Reussi)
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
    });

    it('rembourse le paiement en trop quand un autre paiement actif existe pour la réservation', function () {
        [$client, , $reservation] = scenarioWebhook();
        $ancien = paiementInitie($client, $reservation);
        $ancien->forceFill(['statut' => StatutPaiement::Echoue])->save();
        $actif = paiementInitie($client, $reservation); // nouveau paiement EN_ATTENTE
        expect($actif->id)->not->toBe($ancien->id);

        appelerWebhook(corpsWebhook($ancien))->assertOk();

        expect($ancien->fresh()->statut)->toBe(StatutPaiement::Rembourse)
            ->and($ancien->fresh()->rembourse_le)->not->toBeNull()
            ->and($actif->fresh()->statut)->toBe(StatutPaiement::EnAttente)
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee)
            ->and($reservation->fresh()->code_reservation)->toBeNull();
    });

    it('garde le paiement reçu mais rembourse quand la réservation n\'est plus confirmable', function (StatutReservation $statut, ?string $code) {
        Event::fake([ReservationConfirmee::class]);
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $reservation->forceFill(['statut' => $statut, 'code_reservation' => $code])->save();

        $espion = espionRemboursement();
        $espion->shouldReceive('rembourser')->once()->with($paiement->reference_dexpay, '4500.00')->andReturn(new ResultatOperation(true, 'RMB-1'));

        appelerWebhook(corpsWebhook($paiement))->assertOk();

        $paiement->refresh();
        expect($paiement->statut)->toBe(StatutPaiement::Rembourse)
            ->and($paiement->paye_le)->not->toBeNull()
            ->and($paiement->rembourse_le)->not->toBeNull()
            ->and($reservation->fresh()->statut)->toBe($statut)
            ->and($reservation->fresh()->code_reservation)->toBe($code);
        Event::assertNotDispatched(ReservationConfirmee::class);
    })->with([
        'annulée' => [StatutReservation::Annulee, null],
        'refusée' => [StatutReservation::Refusee, null],
        'déjà confirmée' => [StatutReservation::Confirmee, 'ABCDEF'],
        'en cours' => [StatutReservation::EnCours, 'ABCDEF'],
    ]);

    it('laisse le paiement REUSSI et répond 200 quand le remboursement échoue, sans secret ni corps dans le journal', function (bool $exception) {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $reservation->forceFill(['statut' => StatutReservation::Annulee])->save();
        $espion = espionRemboursement();
        $espion->shouldReceive('rembourser')->once()->andReturnUsing(fn () => $exception
            ? throw new RuntimeException('secret-factice-de-test '.SECRET_WEBHOOK_TEST)
            : new ResultatOperation(false));
        $spy = Log::spy();

        appelerWebhook(corpsWebhook($paiement))->assertOk();

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Reussi)->and($paiement->fresh()->rembourse_le)->toBeNull();
        $spy->shouldHaveReceived('error')->once()->withArgs(function (string $message, array $contexte) use ($paiement) {
            return $contexte['evenement'] === 'dexpay.remboursement_echec'
                && $contexte['paiement_id'] === $paiement->id
                && ! str_contains(json_encode([$message, $contexte]), SECRET_WEBHOOK_TEST)
                && ! str_contains(json_encode([$message, $contexte]), (string) $paiement->reference_dexpay);
        });
    })->with(['refus du prestataire' => [false], 'exception' => [true]]);

    it('ne rembourse jamais deux fois un paiement déjà remboursé', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $paiement->forceFill(['statut' => StatutPaiement::Reussi, 'paye_le' => now()])->save();
        $espion = espionRemboursement();
        $espion->shouldReceive('rembourser')->once()->andReturn(new ResultatOperation(true, 'RMB-1'));
        $service = app(RemboursementPaiementService::class);

        expect($service->rembourser($paiement))->toBeTrue()
            ->and($service->rembourser($paiement))->toBeTrue()
            ->and($service->rembourser($paiement->fresh()))->toBeTrue()
            ->and($paiement->fresh()->statut)->toBe(StatutPaiement::Rembourse);
    });

    it('ne rembourse pas un paiement qui n\'est pas réussi', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $espion = espionRemboursement();
        $espion->shouldNotReceive('rembourser');

        expect(app(RemboursementPaiementService::class)->rembourser($paiement))->toBeFalse()
            ->and($paiement->fresh()->statut)->toBe(StatutPaiement::EnAttente);
    });

    it('recommence avec un autre code en cas de collision sur l\'index unique', function () {
        Reservation::factory()->confirmee()->create(['code_reservation' => 'ABCDEF']);
        $this->mock(GenerateurCodeReservation::class, fn (MockInterface $m) => $m->shouldReceive('generer')->twice()->andReturn('ABCDEF', 'GHJKMN'));
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);

        appelerWebhook(corpsWebhook($paiement))->assertOk()->assertJsonPath('message', 'Paiement confirmé.');

        expect($reservation->fresh()->code_reservation)->toBe('GHJKMN')->and($paiement->fresh()->statut)->toBe(StatutPaiement::Reussi);
    });

    it('abandonne après 5 collisions : erreur 500 journalisée sans le code, tout est annulé', function () {
        Reservation::factory()->confirmee()->create(['code_reservation' => 'ABCDEF']);
        $this->mock(GenerateurCodeReservation::class, fn (MockInterface $m) => $m->shouldReceive('generer')->times(5)->andReturn('ABCDEF'));
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $spy = Log::spy();

        appelerWebhook(corpsWebhook($paiement))->assertStatus(500);

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::EnAttente)->and($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee);
        $spy->shouldHaveReceived('error')->atLeast()->once()->withArgs(fn (string $message, array $contexte) => array_keys($contexte) === ['paiement_id']
            && ! str_contains(json_encode([$message, $contexte]), 'ABCDEF'));
    });

    it('est atomique : un échec après la mise à jour du paiement annule tout', function () {
        Event::fake([ReservationConfirmee::class]);
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        DB::listen(function ($requete) {
            if (str_starts_with($requete->sql, 'update "reservations"')) {
                throw new RuntimeException('Panne simulée.');
            }
        });

        appelerWebhook(corpsWebhook($paiement))->assertStatus(500);

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::EnAttente)
            ->and($paiement->fresh()->paye_le)->toBeNull()
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee)
            ->and($reservation->fresh()->code_reservation)->toBeNull();
        Event::assertNotDispatched(ReservationConfirmee::class);
    });
});

describe('événement échoué', function () {
    it('passe le paiement à echoue, laisse la réservation ACCEPTEE et permet de repayer', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);

        appelerWebhook(corpsWebhook($paiement, 'echoue'))->assertOk();

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Echoue)
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee)
            ->and($reservation->fresh()->code_reservation)->toBeNull();

        $this->actingAs($client, 'sanctum');
        $this->getJson('/api/v1/reservations/'.$reservation->id)->assertJsonPath('peutPayer', true);
        $this->postJson('/api/v1/reservations/'.$reservation->id.'/payer')->assertCreated();
        expect(Paiement::count())->toBe(2)->and(Paiement::where('statut', 'en_attente')->count())->toBe(1);
    });

    it('ne touche jamais un paiement réussi ou remboursé (événement arrivé dans le désordre)', function (StatutPaiement $statut) {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $paiement->forceFill(['statut' => $statut, 'paye_le' => now()])->save();

        appelerWebhook(corpsWebhook($paiement, 'echoue'))->assertOk();

        expect($paiement->fresh()->statut)->toBe($statut);
    })->with([StatutPaiement::Reussi, StatutPaiement::Rembourse]);

    it('ne touche pas non plus un paiement qui vient d\'être confirmé par un « réussi » précédent', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        appelerWebhook(corpsWebhook($paiement))->assertOk();

        appelerWebhook(corpsWebhook($paiement, 'echoue'))->assertOk();

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Reussi)->and($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
    });
});

describe('parcours complet', function () {
    it('accepter, payer, webhook, démarrer avec le code généré, terminer', function () {
        $client = User::factory()->create();
        $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
        $prestation = Prestation::factory()->for($prestataire)->create(['prix' => '15000.00']);
        $debut = now()->addDays(2);
        $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
        $reservation = Reservation::factory()->create([
            'client_id' => $client->id, 'creneau_id' => $creneau->id, 'prestation_id' => $prestation->id,
        ]);

        $this->actingAs($prestataire->user, 'sanctum');
        $this->postJson('/api/v1/prestataire/reservations/'.$reservation->id.'/accepter')->assertOk()->assertJsonPath('statut', 'acceptee');

        $paiement = paiementInitie($client, $reservation);
        appelerWebhook(corpsWebhook($paiement))->assertOk();
        $code = $reservation->fresh()->code_reservation;

        $this->actingAs($prestataire->user, 'sanctum');
        $this->postJson('/api/v1/prestataire/reservations/'.$reservation->id.'/demarrer', ['code' => 'AAAAAA'])->assertStatus(422);
        $this->postJson('/api/v1/prestataire/reservations/'.$reservation->id.'/demarrer', ['code' => strtolower($code)])->assertOk()->assertJsonPath('statut', 'en_cours');
        $this->postJson('/api/v1/prestataire/reservations/'.$reservation->id.'/terminer')->assertOk()->assertJsonPath('statut', 'terminee');
    });

    it('n\'offre aucune route client pour confirmer un paiement', function () {
        [$client, , $reservation] = scenarioWebhook();
        $paiement = paiementInitie($client, $reservation);
        $this->actingAs($client, 'sanctum');

        foreach (['confirmer', 'confirmation', 'valider'] as $suffixe) {
            $this->postJson('/api/v1/reservations/'.$reservation->id.'/'.$suffixe)->assertNotFound();
        }
        expect($paiement->fresh()->statut)->toBe(StatutPaiement::EnAttente);
    });
});
