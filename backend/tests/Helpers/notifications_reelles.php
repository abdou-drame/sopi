<?php

use App\Services\Notification\EnvoiEmail;
use App\Services\Notification\EnvoiPush;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const FCM_EMAIL_COMPTE = 'compte-secret@projet-factice.iam.gserviceaccount.com';

function configurerSmtp(array $surcharge = []): void
{
    config(array_merge([
        'sopi.notifications.email_driver' => 'smtp',
        'sopi.notifications.push_driver' => 'log',
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'smtp.exemple.test',
        'mail.mailers.smtp.port' => 587,
        'mail.mailers.smtp.username' => 'utilisateur-secret-smtp',
        'mail.mailers.smtp.password' => 'motdepasse-secret-smtp',
        'mail.from.address' => 'no-reply@sopi.test',
    ], $surcharge));
    app()->forgetInstance(EnvoiEmail::class);
}

function cleFactice(): string
{
    static $pem = null;
    if ($pem === null) {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        // Windows (XAMPP) : openssl.cnf n'est pas toujours trouvé tout seul.
        $cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (! getenv('OPENSSL_CONF') && is_file($cnf)) {
            $options['config'] = $cnf;
        }
        $cle = openssl_pkey_new($options);
        openssl_pkey_export($cle, $pem, null, $options);
    }

    return $pem;
}

function configurerFcm(?string $credentials = null, array $surcharge = []): void
{
    $credentials ??= json_encode(['project_id' => 'projet-factice', 'client_email' => FCM_EMAIL_COMPTE, 'private_key' => cleFactice(), 'token_uri' => 'https://oauth2.test/token']);
    config(array_merge(['sopi.notifications.push_driver' => 'fcm', 'sopi.notifications.fcm.credentials' => $credentials, 'sopi.notifications.fcm.project_id' => null], $surcharge));
    app()->forgetInstance(EnvoiPush::class);
    Cache::flush();
    Http::preventStrayRequests();
}

function fauxFcm(array $reponsesEnvoi): void
{
    Http::fake([
        'oauth2.test/*' => Http::response(['access_token' => 'jeton-acces-secret', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::sequence($reponsesEnvoi),
    ]);
}
