<?php

use App\Exceptions\ConfigurationPaiementInvalide;
use App\Services\Paiement\DexPay\DexPayClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const AD_DEFAUT = 'https://api.dexpay.africa/api/v1';

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'sopi.paiement.dexpay.public_key' => 'pk_factice_ADRESSE',
        'sopi.paiement.dexpay.secret_key' => 'sk_factice_ADRESSE',
    ]);
});

function adUrlCreationSession(): string
{
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['payment_url' => 'https://pay.exemple.test/x']], 201)]);
    DexPayClient::depuisConfiguration()->post('/checkout-sessions', ['reference' => 'SOPI-PAY-test']);

    $url = null;
    Http::assertSent(function (Request $requete) use (&$url) {
        $url = $requete->url();

        return true;
    });

    return $url;
}

it('utilise l\'adresse de production par défaut dans la configuration quand DEXPAY_BASE_URL est vide', function () {
    expect(config('sopi.paiement.dexpay.base_url'))->toBe(AD_DEFAUT);
});

it('utilise l\'adresse par défaut si la valeur est absente, vide ou ne contient que des espaces', function (mixed $valeur) {
    config(['sopi.paiement.dexpay.base_url' => $valeur]);

    expect(adUrlCreationSession())->toBe(AD_DEFAUT.'/checkout-sessions');
})->with([null, '', '   ']);

it('utilise l\'adresse fournie (bac à sable) pour la création de session', function () {
    config(['sopi.paiement.dexpay.base_url' => 'https://api-sandbox.dexpay.africa/api/v1']);

    expect(adUrlCreationSession())->toBe('https://api-sandbox.dexpay.africa/api/v1/checkout-sessions');
});

it('envoie la clé publique dans x-api-key et pas la clé secrète pour la création de session', function () {
    adUrlCreationSession();

    Http::assertSent(fn (Request $r) => $r->hasHeader('x-api-key', 'pk_factice_ADRESSE') && ! $r->hasHeader('x-api-secret'));
});

it('refuse une adresse invalide avec un message clair qui ne cite pas la valeur', function (string $valeur) {
    config(['sopi.paiement.dexpay.base_url' => $valeur]);

    try {
        DexPayClient::depuisConfiguration();
        $this->fail('Une ConfigurationPaiementInvalide était attendue.');
    } catch (ConfigurationPaiementInvalide $e) {
        expect($e->variablesManquantes)->toBe(['DEXPAY_BASE_URL'])
            ->and($e->getMessage())->toContain('https://')->toContain('DEXPAY_BASE_URL')
            ->and($e->getMessage())->not->toContain('pk_factice')->not->toContain('sk_factice');
    }
})->with([
    'sans schéma' => 'api.dexpay.africa/api/v1',
    'http en clair' => 'http://api.dexpay.africa/api/v1',
    'chemin relatif' => '/api/v1',
    'barre finale' => 'https://api.dexpay.africa/api/v1/',
    'schéma seul' => 'https://',
    'avec espace' => 'https://api.dexpay.africa/api v1',
]);
