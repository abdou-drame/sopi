<?php

use App\Console\Commands\RembourserPaiementsDus;
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
use App\Services\Paiement\ResultatOperation;
use App\Services\RemboursementPaiementService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

const SECRET_DUS_TEST = 'secret-factice-dus';

beforeEach(function () {
    Http::preventStrayRequests();
    Carbon::setTestNow(now());
    config(['sopi.paiement.driver' => 'fake', 'sopi.paiement.webhook_secret' => SECRET_DUS_TEST]);
    app()->forgetInstance(PaiementService::class);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Faux prestataire de paiement : chaque appel est ajouté à $appels ; $echecs liste les références dont le remboursement
 * échoue (un tableau passé par référence, modifiable pendant le test).
 */
function prestataireDeRemboursement(array &$appels, array &$echecs = []): MockInterface
{
    $espion = Mockery::mock(FakePaiementService::class, [SECRET_DUS_TEST])->makePartial();
    $espion->shouldReceive('rembourser')->andReturnUsing(function (string $reference, string $montant) use (&$appels, &$echecs) {
        $appels[] = $reference;

        return new ResultatOperation(! in_array($reference, $echecs, true), 'RMB-TEST');
    });
    app()->instance(PaiementService::class, $espion);

    return $espion;
}

/** Réservation ACCEPTEE (créneau réservé dans 2 jours) avec un paiement EN_ATTENTE qui a une référence. */
function reservationAvecPaiementEnAttente(): array
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $prestation = Prestation::factory()->for($prestataire)->create(['prix' => '15000.00']);
    $debut = now()->addDays(2);
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
    $reservation = Reservation::factory()->acceptee()->create(['client_id' => User::factory()->create()->id, 'creneau_id' => $creneau->id, 'prestation_id' => $prestation->id]);
    $paiement = Paiement::factory()->enAttente()->create(['reservation_id' => $reservation->id, 'montant_acompte' => '4500.00', 'reference_dexpay' => 'REF-'.strtoupper(bin2hex(random_bytes(6)))]);

    return [$reservation, $paiement];
}

function evenementPaye(Paiement $paiement): TestResponse
{
    $corps = json_encode(['reference' => $paiement->reference_dexpay, 'statut' => 'reussi', 'montant' => '4500.00']);

    return test()->call('POST', '/api/v1/webhooks/paiement', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $corps, SECRET_DUS_TEST),
    ], $corps);
}

/** Paiement dû, REUSSI par défaut, dont le dernier essai (updated_at) remonte à $minutes minutes. */
function paiementDu(string $etat = 'reussi', int $minutes = 10, int $tentatives = 0, ?Reservation $reservation = null): Paiement
{
    $paiement = Paiement::factory()->{$etat}()->create([
        'reservation_id' => ($reservation ?? Reservation::factory()->annulee()->create())->id,
        'remboursement_du_le' => now()->subMinutes($minutes + 1),
        'remboursement_tentatives' => $tentatives,
    ]);
    Paiement::query()->whereKey($paiement->id)->update(['updated_at' => now()->subMinutes($minutes)]);

    return $paiement->refresh();
}

describe('webhook : marquage du remboursement dû', function () {
    it('marque dû et rembourse un paiement reçu pour une réservation non confirmable', function () {
        $appels = [];
        prestataireDeRemboursement($appels);
        [$reservation, $paiement] = reservationAvecPaiementEnAttente();
        $reservation->forceFill(['statut' => StatutReservation::Annulee])->save();

        evenementPaye($paiement)->assertOk();

        $paiement->refresh();
        expect($paiement->statut)->toBe(StatutPaiement::Rembourse)->and($paiement->remboursement_du_le)->not->toBeNull()
            ->and($appels)->toBe([$paiement->reference_dexpay]);
    });

    it('laisse un remboursement échoué marqué dû, puis la commande le reprend', function () {
        $appels = [];
        $echecs = [];
        prestataireDeRemboursement($appels, $echecs);
        [$reservation, $paiement] = reservationAvecPaiementEnAttente();
        $reservation->forceFill(['statut' => StatutReservation::Annulee])->save();
        $echecs = [$paiement->reference_dexpay];

        evenementPaye($paiement)->assertOk();

        $paiement->refresh();
        expect($paiement->statut)->toBe(StatutPaiement::Reussi)->and($paiement->remboursement_du_le)->not->toBeNull()
            ->and($paiement->remboursement_tentatives)->toBe(0);

        $echecs = [];
        $this->travel(6)->minutes();
        $this->artisan('sopi:paiements:rembourser-dus')->assertSuccessful();
        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Rembourse)->and($paiement->fresh()->remboursement_tentatives)->toBe(1);
    });

    it('marque dû un paiement en trop (ECHOUE), le laisse retrouvable en cas d\'échec et la commande le rembourse depuis ECHOUE', function () {
        $appels = [];
        $echecs = [];
        prestataireDeRemboursement($appels, $echecs);
        [$reservation, $ancien] = reservationAvecPaiementEnAttente();
        $ancien->forceFill(['statut' => StatutPaiement::Echoue])->save();
        $actif = Paiement::factory()->enAttente()->create(['reservation_id' => $reservation->id, 'montant_acompte' => '4500.00', 'reference_dexpay' => 'REF-ACTIF']);
        $echecs = [$ancien->reference_dexpay];

        evenementPaye($ancien)->assertOk();

        $ancien->refresh();
        expect($ancien->statut)->toBe(StatutPaiement::Echoue)->and($ancien->remboursement_du_le)->not->toBeNull()
            ->and($actif->fresh()->remboursement_du_le)->toBeNull()
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee);

        $echecs = [];
        $this->travel(6)->minutes();
        $this->artisan('sopi:paiements:rembourser-dus')->assertSuccessful();
        expect($ancien->fresh()->statut)->toBe(StatutPaiement::Rembourse)->and($actif->fresh()->statut)->toBe(StatutPaiement::EnAttente);
    });

    it('ne marque jamais un paiement confirmé normalement', function () {
        $appels = [];
        prestataireDeRemboursement($appels);
        [$reservation, $paiement] = reservationAvecPaiementEnAttente();

        evenementPaye($paiement)->assertOk();

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Reussi)->and($paiement->fresh()->remboursement_du_le)->toBeNull()
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee)->and($appels)->toBe([]);
    });
});

describe('commande sopi:paiements:rembourser-dus', function () {
    it('rembourse un paiement dû REUSSI ou ECHOUE et affiche seulement des nombres', function () {
        $appels = [];
        prestataireDeRemboursement($appels);
        $reussi = paiementDu('reussi');
        $echoue = paiementDu('echoue');

        $this->artisan('sopi:paiements:rembourser-dus')
            ->expectsOutput('2 remboursement(s) repris : 2 réussi(s), 0 échoué(s).')
            ->assertSuccessful();

        expect($reussi->fresh()->statut)->toBe(StatutPaiement::Rembourse)->and($echoue->fresh()->statut)->toBe(StatutPaiement::Rembourse)
            ->and($reussi->fresh()->rembourse_le)->not->toBeNull()->and($appels)->toHaveCount(2);
    });

    it('ne rembourse jamais un paiement non dû', function (string $etat) {
        $appels = [];
        prestataireDeRemboursement($appels);
        $paiement = Paiement::factory()->{$etat}()->create(['reservation_id' => Reservation::factory()->annulee()->create()->id]);
        Paiement::query()->whereKey($paiement->id)->update(['updated_at' => now()->subHour()]);

        $this->artisan('sopi:paiements:rembourser-dus')->expectsOutput('0 remboursement(s) repris : 0 réussi(s), 0 échoué(s).')->assertSuccessful();

        expect($appels)->toBe([])->and($paiement->fresh()->statut->value)->toBe($paiement->statut->value)->and($paiement->fresh()->remboursement_tentatives)->toBe(0);
    })->with(['reussi', 'echoue', 'enAttente', 'rembourse']);

    it('ne reprend pas un paiement déjà remboursé même marqué dû', function () {
        $appels = [];
        prestataireDeRemboursement($appels);
        $paiement = paiementDu('rembourse');

        $this->artisan('sopi:paiements:rembourser-dus')->assertSuccessful();

        expect($appels)->toBe([])->and($paiement->fresh()->remboursement_tentatives)->toBe(0);
    });

    it('respecte le délai entre deux essais', function () {
        config(['sopi.paiement.remboursement_delai_minutes' => 5]);
        $appels = [];
        prestataireDeRemboursement($appels);
        $recent = paiementDu('reussi', 4);
        $ancien = paiementDu('reussi', 5);

        $this->artisan('sopi:paiements:rembourser-dus')->assertSuccessful();

        expect($appels)->toBe([$ancien->reference_dexpay])->and($recent->fresh()->statut)->toBe(StatutPaiement::Reussi)->and($recent->fresh()->remboursement_tentatives)->toBe(0);
    });

    it('compte les essais, attend le délai entre deux essais et s\'arrête au maximum avec un journal d\'intervention manuelle', function () {
        config(['sopi.paiement.remboursement_max_tentatives' => 2, 'sopi.paiement.remboursement_delai_minutes' => 5]);
        $appels = [];
        $echecs = [];
        prestataireDeRemboursement($appels, $echecs);
        $paiement = paiementDu('reussi', 10);
        $echecs = [$paiement->reference_dexpay];
        $espion = Log::spy();

        $this->artisan('sopi:paiements:rembourser-dus')->expectsOutput('1 remboursement(s) repris : 0 réussi(s), 1 échoué(s).')->assertSuccessful();
        expect($paiement->fresh()->remboursement_tentatives)->toBe(1);

        $this->artisan('sopi:paiements:rembourser-dus')->assertSuccessful(); // trop tôt : le délai n'est pas écoulé
        expect($appels)->toHaveCount(1);

        $this->travel(6)->minutes();
        $this->artisan('sopi:paiements:rembourser-dus')->assertSuccessful();
        expect($paiement->fresh()->remboursement_tentatives)->toBe(2)->and($appels)->toHaveCount(2);

        $this->travel(6)->minutes();
        $this->artisan('sopi:paiements:rembourser-dus')->expectsOutput('0 remboursement(s) repris : 0 réussi(s), 0 échoué(s).')->assertSuccessful();
        expect($appels)->toHaveCount(2)->and($paiement->fresh()->statut)->toBe(StatutPaiement::Reussi);
        $espion->shouldHaveReceived('error')->withArgs(fn (string $message, array $contexte) => $message === 'Remboursement : intervention manuelle requise.'
            && $contexte === ['paiement_id' => $paiement->id])->atLeast()->once();
    });

    it('traite au plus 100 paiements par exécution', function () {
        $appels = [];
        prestataireDeRemboursement($appels);
        $reservation = Reservation::factory()->annulee()->create();
        Paiement::factory()->count(RembourserPaiementsDus::TAILLE_LOT + 1)->echoue()->create([
            'reservation_id' => $reservation->id, 'remboursement_du_le' => now()->subHour(), 'updated_at' => now()->subHour(),
        ]);

        $this->artisan('sopi:paiements:rembourser-dus')->expectsOutput('100 remboursement(s) repris : 100 réussi(s), 0 échoué(s).')->assertSuccessful();
        expect(Paiement::query()->where('statut', StatutPaiement::Rembourse->value)->count())->toBe(100);

        $this->artisan('sopi:paiements:rembourser-dus')->expectsOutput('1 remboursement(s) repris : 1 réussi(s), 0 échoué(s).')->assertSuccessful();
        expect(Paiement::query()->where('statut', StatutPaiement::Rembourse->value)->count())->toBe(101);
    });

    it('isole les erreurs : une exception sur un paiement n\'empêche pas les suivants', function () {
        $premier = paiementDu('reussi', 20);
        $second = paiementDu('reussi', 10);
        $appels = [];
        $service = Mockery::mock(RemboursementPaiementService::class);
        $service->shouldReceive('rembourser')->andReturnUsing(function (Paiement $paiement) use ($premier, &$appels) {
            $appels[] = $paiement->id;
            if ($paiement->id === $premier->id) {
                throw new RuntimeException('panne '.SECRET_DUS_TEST);
            }
            $paiement->rembourserSiStatut(StatutPaiement::Reussi);

            return true;
        });
        app()->instance(RemboursementPaiementService::class, $service);
        $espion = Log::spy();

        $this->artisan('sopi:paiements:rembourser-dus')->expectsOutput('2 remboursement(s) repris : 1 réussi(s), 1 échoué(s).')->assertSuccessful();

        expect($appels)->toBe([$premier->id, $second->id])->and($second->fresh()->statut)->toBe(StatutPaiement::Rembourse)
            ->and($premier->fresh()->statut)->toBe(StatutPaiement::Reussi)->and($premier->fresh()->remboursement_tentatives)->toBe(1);
        $espion->shouldHaveReceived('error')->withArgs(fn (string $message, array $contexte) => ! str_contains(json_encode([$message, $contexte]), SECRET_DUS_TEST)
            && $contexte['paiement_id'] === $premier->id)->atLeast()->once();
    });

    it('est idempotente : deux exécutions de suite ne remboursent qu\'une fois', function () {
        $appels = [];
        prestataireDeRemboursement($appels);
        paiementDu('reussi');

        $this->artisan('sopi:paiements:rembourser-dus')->assertSuccessful();
        $this->artisan('sopi:paiements:rembourser-dus')->expectsOutput('0 remboursement(s) repris : 0 réussi(s), 0 échoué(s).')->assertSuccessful();

        expect($appels)->toHaveCount(1);
    });

    it('est planifiée toutes les 5 minutes sans chevauchement', function () {
        $evenement = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'sopi:paiements:rembourser-dus'));

        expect($evenement)->not->toBeNull()->and($evenement->expression)->toBe('*/5 * * * *')->and($evenement->withoutOverlapping)->toBeTrue();
    });
});
