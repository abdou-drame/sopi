<?php

use App\Enums\StatutPaiement;
use App\Enums\StatutRetrait;
use App\Exceptions\DexPayErreur;
use App\Exceptions\EvenementPaiementIllisible;
use App\Services\Paiement\DexPay\DexPayClient;
use App\Services\Paiement\DexPay\DexPayPaiementService;
use App\Services\Paiement\GenreEvenement;
use App\Services\Paiement\PaiementService;
use App\Services\Paiement\SessionPaiement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const DX_CLE_PUBLIQUE = 'pk_factice_dx';
const DX_CLE_SECRETE = 'sk_factice_dx';

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'app.url' => 'https://sopi.test',
        'sopi.paiement.driver' => 'dexpay',
        'sopi.paiement.dexpay.base_url' => 'https://dexpay.test/api/v1',
        'sopi.paiement.dexpay.public_key' => DX_CLE_PUBLIQUE,
        'sopi.paiement.dexpay.secret_key' => DX_CLE_SECRETE,
        'sopi.paiement.dexpay.amount_factor' => 1,
        'sopi.paiement.dexpay.success_url' => '',
        'sopi.paiement.dexpay.failure_url' => '',
    ]);
    app()->forgetInstance(PaiementService::class);
});

function dxService(): DexPayPaiementService
{
    return DexPayPaiementService::depuisConfiguration();
}

function dxSession(): SessionPaiement
{
    return dxService()->creerSession('res-1', '3000.00', 'Acompte de réservation Sopi', '0198a1b2-0000-7000-8000-000000000001');
}

describe('création de la session de paiement', function () {
    it('envoie le bon corps, avec la seule clé publique, sans donnée personnelle', function () {
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'success', 'data' => ['reference' => 'SOPI-PAY-0198a1b2-0000-7000-8000-000000000001', 'payment_url' => 'https://pay.dexpay.test/abc']], 201)]);

        $session = dxSession();

        expect($session->urlPaiement)->toBe('https://pay.dexpay.test/abc')->and($session->reference)->toBe('SOPI-PAY-0198a1b2-0000-7000-8000-000000000001');
        Http::assertSent(function (Request $r) {
            $corps = $r->data();

            return $r->method() === 'POST'
                && $r->url() === 'https://dexpay.test/api/v1/checkout-sessions'
                && $r->header('x-api-key') === [DX_CLE_PUBLIQUE]
                && $r->header('x-api-secret') === []
                && $corps['reference'] === 'SOPI-PAY-0198a1b2-0000-7000-8000-000000000001'
                && strlen($corps['reference']) <= 64
                && $corps['amount'] === 3000
                && $corps['currency'] === 'XOF'
                && $corps['webhook_url'] === 'https://sopi.test/api/v1/webhooks/paiement'
                && $corps['success_url'] === 'https://sopi.test/paiement/succes'
                && $corps['failure_url'] === 'https://sopi.test/paiement/echec'
                && $corps['metadata'] === ['paiement_id' => '0198a1b2-0000-7000-8000-000000000001']
                && $corps['item_name'] === 'Acompte de réservation Sopi'
                && ! str_contains($r->body(), DX_CLE_SECRETE);
        });
    });

    it('utilise les URLs de retour configurées et applique le facteur de montant', function () {
        config(['sopi.paiement.dexpay.success_url' => 'https://sopi.test/ok', 'sopi.paiement.dexpay.failure_url' => 'https://sopi.test/ko', 'sopi.paiement.dexpay.amount_factor' => 100]);
        Http::fake(['dexpay.test/*' => Http::response(['payment_url' => 'https://pay.dexpay.test/abc'], 201)]);

        dxSession();

        Http::assertSent(fn (Request $r) => $r['amount'] === 300000 && $r['success_url'] === 'https://sopi.test/ok' && $r['failure_url'] === 'https://sopi.test/ko');
    });

    it('prend l\'adresse du bac à sable quand isSandbox est vrai', function () {
        Http::fake(['dexpay.test/*' => Http::response(['data' => ['payment_url' => 'https://pay.dexpay.test/reel', 'sandbox_payment_url' => 'https://pay.dexpay.test/test', 'isSandbox' => true]], 201)]);

        expect(dxSession()->urlPaiement)->toBe('https://pay.dexpay.test/test');
    });

    it('refuse un montant avec des centimes de franc', function () {
        Http::fake();

        expect(fn () => dxService()->creerSession('r', '3000.50', 'x', 'p'))->toThrow(DexPayErreur::class);
        Http::assertNothingSent();
    });

    it('lève une erreur sans fuite pour 400, 401, 409, 422 et 500', function (int $code) {
        Http::fake(['dexpay.test/*' => Http::response(['message' => 'detail interne '.DX_CLE_SECRETE], $code)]);

        try {
            dxSession();
            $this->fail('Une exception était attendue.');
        } catch (DexPayErreur $e) {
            expect($e->statutHttp)->toBe($code)->and($e->getMessage())->not->toContain(DX_CLE_SECRETE)->not->toContain(DX_CLE_PUBLIQUE);
        }
        Http::assertSentCount(1);
    })->with([400, 401, 409, 422, 500]);

    it('lève une erreur sur un délai dépassé, sans nouvel essai', function () {
        $appels = 0;
        Http::fake(['dexpay.test/*' => function () use (&$appels) {
            $appels++;

            throw new ConnectionException('délai');
        }]);

        expect(fn () => dxSession())->toThrow(DexPayErreur::class)->and($appels)->toBe(1);
    });

    it('lève une erreur si la réponse ne contient pas d\'adresse de paiement', function () {
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'success', 'data' => []], 201)]);

        expect(fn () => dxSession())->toThrow(DexPayErreur::class);
    });
});

describe('signature du webhook', function () {
    $corps = '{"event":"checkout.completed","reference":"SOPI-PAY-1","amount":3000,"currency":"XOF"}';

    it('accepte la signature HMAC-SHA256 hexadécimale du corps brut', function () use ($corps) {
        expect(dxService()->verifierSignature($corps, hash_hmac('sha256', $corps, DX_CLE_SECRETE)))->toBeTrue()
            ->and(dxService()->verifierSignature($corps, strtoupper(hash_hmac('sha256', $corps, DX_CLE_SECRETE))))->toBeTrue();
    });

    it('refuse une signature fausse, absente ou vide', function (?string $signature) use ($corps) {
        expect(dxService()->verifierSignature($corps, $signature))->toBeFalse();
    })->with([null, '', '   ', 'abc', 'sha256=deadbeef']);

    it('refuse un corps modifié', function () use ($corps) {
        $signature = hash_hmac('sha256', $corps, DX_CLE_SECRETE);

        expect(dxService()->verifierSignature(str_replace('3000', '1', $corps), $signature))->toBeFalse();
    });

    it('refuse une signature faite avec une autre clé', function () use ($corps) {
        expect(dxService()->verifierSignature($corps, hash_hmac('sha256', $corps, 'autre-cle')))->toBeFalse();
    });

    it('refuse tout quand la clé secrète est absente', function () use ($corps) {
        $service = new DexPayPaiementService(Mockery::mock(DexPayClient::class), '');

        expect($service->verifierSignature($corps, hash_hmac('sha256', $corps, '')))->toBeFalse();
    });
});

describe('analyse des événements', function () {
    it('traduit chaque événement DexPay', function (string $evenement, GenreEvenement $genre, ?StatutPaiement $statut, ?StatutRetrait $versement) {
        $corps = json_encode(['event' => $evenement, 'reference' => 'SOPI-PAY-1', 'amount' => 3000, 'currency' => 'XOF', 'metadata' => ['retrait_id' => '0198a1b2-0000-7000-8000-000000000009']]);

        $resultat = dxService()->analyserEvenement($corps);

        expect($resultat->genre)->toBe($genre)->and($resultat->statutVersement)->toBe($versement);
        if ($statut !== null) {
            expect($resultat->statut)->toBe($statut)->and($resultat->montant)->toBe('3000.00');
        }
    })->with([
        'terminé' => ['checkout.completed', GenreEvenement::Paiement, StatutPaiement::Reussi, null],
        'échoué' => ['checkout.failed', GenreEvenement::Paiement, StatutPaiement::Echoue, null],
        'annulé' => ['checkout.cancelled', GenreEvenement::Paiement, StatutPaiement::Echoue, null],
        'remboursé' => ['checkout.refunded', GenreEvenement::Remboursement, StatutPaiement::Rembourse, null],
        'initié' => ['checkout.initiated', GenreEvenement::Ignore, null, null],
        'inconnu' => ['truc.inconnu', GenreEvenement::Ignore, null, null],
        'versement réussi' => ['payout.completed', GenreEvenement::Versement, null, StatutRetrait::Reussi],
        'versement échoué' => ['payout.failed', GenreEvenement::Versement, null, StatutRetrait::Echoue],
        'versement annulé' => ['payout.cancelled', GenreEvenement::Versement, null, StatutRetrait::Echoue],
    ]);

    it('refuse un événement illisible ou incomplet', function (string $corps) {
        expect(fn () => dxService()->analyserEvenement($corps))->toThrow(EvenementPaiementIllisible::class);
    })->with([
        'pas du JSON' => ['pas du json'],
        'liste' => ['[]'],
        'sans event' => ['{"reference":"X","amount":3000}'],
        'sans référence' => ['{"event":"checkout.completed","amount":3000,"currency":"XOF"}'],
        'sans montant' => ['{"event":"checkout.completed","reference":"X","currency":"XOF"}'],
        'montant nul' => ['{"event":"checkout.completed","reference":"X","amount":0,"currency":"XOF"}'],
        'montant texte' => ['{"event":"checkout.completed","reference":"X","amount":"beaucoup","currency":"XOF"}'],
        'autre devise' => ['{"event":"checkout.completed","reference":"X","amount":3000,"currency":"EUR"}'],
        'versement sans référence' => ['{"event":"payout.completed"}'],
    ]);

    it('lit un montant décimal ou textuel de la même façon', function (mixed $montant) {
        $corps = json_encode(['event' => 'checkout.completed', 'reference' => 'X', 'amount' => $montant, 'currency' => 'XOF']);

        expect(dxService()->analyserEvenement($corps)->montant)->toBe('3000.00');
    })->with([3000, 3000.0, '3000', '3000.00']);
});
