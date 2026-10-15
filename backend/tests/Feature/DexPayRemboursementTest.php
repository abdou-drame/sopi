<?php

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Services\Paiement\PaiementService;
use App\Services\RemboursementPaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const CLE_PUBLIQUE_RMB = 'pk_factice_rmb';
const CLE_SECRETE_RMB = 'sk_factice_rmb';

beforeEach(function () {
    config([
        'sopi.paiement.driver' => 'dexpay',
        'sopi.paiement.dexpay.base_url' => 'https://dexpay.test/api/v1',
        'sopi.paiement.dexpay.public_key' => CLE_PUBLIQUE_RMB,
        'sopi.paiement.dexpay.secret_key' => CLE_SECRETE_RMB,
        'sopi.paiement.webhook_secret' => CLE_SECRETE_RMB,
    ]);
    app()->forgetInstance(PaiementService::class);
});

function webhookRemboursement(string $reference): TestResponse
{
    $corps = json_encode(['event' => 'checkout.refunded', 'reference' => $reference, 'amount' => 3000, 'currency' => 'XOF']);

    return test()->call('POST', '/api/v1/webhooks/paiement', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $corps, CLE_SECRETE_RMB),
    ], $corps);
}

describe('remboursement chez DexPay', function () {
    it('appelle l\'endpoint de remboursement avec les deux clés et passe le paiement à REMBOURSE', function () {
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'success', 'data' => ['status' => 'success']], 200)]);
        $paiement = Paiement::factory()->reussi()->create();

        $ok = app(RemboursementPaiementService::class)->rembourser($paiement);

        expect($ok)->toBeTrue()->and($paiement->refresh()->statut)->toBe(StatutPaiement::Rembourse);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === 'https://dexpay.test/api/v1/checkout-sessions/refund/'.rawurlencode($paiement->reference_dexpay)
            && $r->header('x-api-key') === [CLE_PUBLIQUE_RMB]
            && $r->header('x-api-secret') === [CLE_SECRETE_RMB]
            && in_array($r->body(), ['', 'null', '[]'], true));
    });

    it('traite un HTTP 200 au statut « failed » comme un échec, sans changer le paiement', function () {
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'failed'], 200)]);
        Log::spy();
        $paiement = Paiement::factory()->reussi()->create();

        expect(app(RemboursementPaiementService::class)->rembourser($paiement))->toBeFalse()
            ->and($paiement->refresh()->statut)->toBe(StatutPaiement::Reussi);
    });

    it('traite 400, 401, 404 et 500 comme un échec sans exception ni changement', function (int $code) {
        Http::fake(['dexpay.test/*' => Http::response(['message' => 'refus'], $code)]);
        $paiement = Paiement::factory()->reussi()->create();

        expect(app(RemboursementPaiementService::class)->rembourser($paiement))->toBeFalse()
            ->and($paiement->refresh()->statut)->toBe(StatutPaiement::Reussi);
    })->with([400, 401, 404, 500]);

    it('traite un délai dépassé comme un échec sans exception', function () {
        Http::fake(['dexpay.test/*' => fn () => throw new ConnectionException('délai dépassé')]);
        $paiement = Paiement::factory()->reussi()->create();

        expect(app(RemboursementPaiementService::class)->rembourser($paiement))->toBeFalse()
            ->and($paiement->refresh()->statut)->toBe(StatutPaiement::Reussi);
    });

    it('ne laisse apparaître aucune clé dans le journal en cas d\'échec', function () {
        Http::fake(['dexpay.test/*' => Http::response(['message' => 'refus'], 401)]);
        Log::spy();
        $paiement = Paiement::factory()->reussi()->create();

        app(RemboursementPaiementService::class)->rembourser($paiement);

        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $contexte = []) => ! str_contains(json_encode($contexte), CLE_SECRETE_RMB)
            && ! str_contains(json_encode($contexte), CLE_PUBLIQUE_RMB))->atLeast()->once();
    });

    it('ne rembourse jamais deux fois un paiement déjà remboursé', function () {
        Http::fake();
        $paiement = Paiement::factory()->rembourse()->create();

        expect(app(RemboursementPaiementService::class)->rembourser($paiement))->toBeTrue();
        Http::assertNothingSent();
    });
});

describe('webhook checkout.refunded', function () {
    it('passe un paiement réussi à REMBOURSE, sans rappeler DexPay', function () {
        Http::fake();
        $paiement = Paiement::factory()->reussi()->create();

        webhookRemboursement($paiement->reference_dexpay)->assertOk()->assertJsonPath('message', 'Remboursement enregistré.');

        expect($paiement->refresh()->statut)->toBe(StatutPaiement::Rembourse)->and($paiement->rembourse_le)->not->toBeNull();
        Http::assertNothingSent();
    });

    it('est idempotent : un rejeu ne change rien', function () {
        Http::fake();
        $paiement = Paiement::factory()->reussi()->create();

        webhookRemboursement($paiement->reference_dexpay)->assertOk();
        $date = $paiement->refresh()->rembourse_le;
        webhookRemboursement($paiement->reference_dexpay)->assertOk()->assertJsonPath('message', 'Déjà traité.');

        expect($paiement->refresh()->rembourse_le->equalTo($date))->toBeTrue();
    });

    it('arrive après notre propre remboursement : pas de double remboursement ni de régression', function () {
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'success'], 200)]);
        $paiement = Paiement::factory()->reussi()->create();

        app(RemboursementPaiementService::class)->rembourser($paiement);
        webhookRemboursement($paiement->reference_dexpay)->assertOk()->assertJsonPath('message', 'Déjà traité.');

        expect($paiement->refresh()->statut)->toBe(StatutPaiement::Rembourse);
        Http::assertSentCount(1);
    });

    it('ne touche pas un paiement encore en attente (événement reçu dans le désordre)', function () {
        Http::fake();
        $paiement = Paiement::factory()->enAttente()->create(['reference_dexpay' => 'SOPI-PAY-TEST']);

        webhookRemboursement('SOPI-PAY-TEST')->assertOk();

        expect($paiement->refresh()->statut)->toBe(StatutPaiement::EnAttente);
    });

    it('ignore une référence inconnue avec un 200', function () {
        Http::fake();

        webhookRemboursement('SOPI-PAY-INCONNU')->assertOk();
    });
});
