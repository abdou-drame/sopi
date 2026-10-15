<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

require_once __DIR__.'/../Helpers/notifications_reelles.php';

it('envoie l\'e-mail de test avec la configuration SMTP', function () {
    configurerSmtp();
    Mail::shouldReceive('raw')->once();

    $this->artisan('sopi:notifications:tester-email', ['adresse' => 'test@exemple.test'])
        ->expectsOutputToContain('E-mail de test envoyé')->assertSuccessful();
});

it('refuse une adresse invalide', function () {
    $this->artisan('sopi:notifications:tester-email', ['adresse' => 'pas-une-adresse'])->expectsOutputToContain('invalide')->assertFailed();
});

it('affiche les variables e-mail manquantes sans valeur secrète', function () {
    configurerSmtp(['mail.mailers.smtp.host' => null]);

    $this->artisan('sopi:notifications:tester-email', ['adresse' => 'test@exemple.test'])
        ->expectsOutputToContain('MAIL_HOST')->assertFailed();
});

it('masque adresse et accès dans l\'erreur du transport', function () {
    configurerSmtp();
    Mail::shouldReceive('raw')->andThrow(new RuntimeException('refus pour test@exemple.test avec motdepasse-secret-smtp'));

    $this->artisan('sopi:notifications:tester-email', ['adresse' => 'test@exemple.test'])
        ->expectsOutputToContain('Échec de l\'envoi')
        ->doesntExpectOutputToContain('motdepasse-secret-smtp')
        ->assertFailed();
});

it('envoie le push de test sans afficher le jeton', function () {
    configurerFcm();
    fauxFcm([Http::response(['name' => 'ok'])]);

    $this->artisan('sopi:notifications:tester-push', ['jeton' => 'jeton-appareil-000000000001'])
        ->expectsOutputToContain('Push de test envoyé')->doesntExpectOutputToContain('jeton-appareil-000000000001')->assertSuccessful();
});

it('signale un jeton refusé par FCM', function () {
    configurerFcm();
    fauxFcm([Http::response(['error' => ['status' => 'NOT_FOUND']], 404)]);

    $this->artisan('sopi:notifications:tester-push', ['jeton' => 'jeton-appareil-000000000001'])->expectsOutputToContain('refuse ce jeton')->assertFailed();
});

it('affiche une configuration FCM absente ou une authentification refusée sans secret', function () {
    configurerFcm('');
    $this->artisan('sopi:notifications:tester-push', ['jeton' => 'jeton-appareil-000000000001'])->expectsOutputToContain('FCM_CREDENTIALS')->assertFailed();

    configurerFcm();
    Cache::flush();
    Http::fake(['oauth2.test/*' => Http::response(['error' => 'invalid_grant', 'error_description' => cleFactice()], 400)]);
    $this->artisan('sopi:notifications:tester-push', ['jeton' => 'jeton-appareil-000000000001'])
        ->expectsOutputToContain('invalid_grant')->doesntExpectOutputToContain('PRIVATE KEY')->assertFailed();
});
