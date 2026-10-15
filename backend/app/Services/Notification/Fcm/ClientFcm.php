<?php

namespace App\Services\Notification\Fcm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Client FCM HTTP v1 (Http de Laravel, sans bibliothèque supplémentaire) : jeton d'accès OAuth2 obtenu avec un JWT RS256 signé par
 * la clé du compte de service, gardé en cache, puis envoi d'un message à un jeton d'appareil. Aucune erreur ne contient de secret.
 */
class ClientFcm
{
    private const PORTEE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CLE_CACHE = 'fcm.jeton_acces';

    public function __construct(private readonly IdentifiantsFcm $identifiants) {}

    /**
     * @param  array<string, string>  $donnees
     * @return string 'ok', 'jeton_invalide' (à supprimer) ou lève FcmErreur (authentification, quota, réseau, serveur)
     */
    public function envoyer(string $jeton, string $titre, string $corps, array $donnees = []): string
    {
        $message = ['token' => $jeton, 'notification' => ['title' => $titre, 'body' => $corps], 'data' => $donnees, 'android' => ['priority' => 'high']];

        $reponse = $this->post($this->urlEnvoi(), ['message' => $message], $this->jetonAcces());
        if ($reponse->status() === 401) {
            // Jeton d'accès périmé : un seul nouvel essai avec un jeton neuf.
            Cache::forget(self::CLE_CACHE);
            $reponse = $this->post($this->urlEnvoi(), ['message' => $message], $this->jetonAcces());
        }

        if ($reponse->successful()) {
            return 'ok';
        }

        $code = (string) ($reponse->json('error.details.0.errorCode') ?? $reponse->json('error.status') ?? 'inconnu');
        if ($reponse->status() === 404 || $code === 'UNREGISTERED' || ($reponse->status() === 400 && $code === 'INVALID_ARGUMENT' && stripos((string) $reponse->json('error.message'), 'token') !== false)) {
            return 'jeton_invalide';
        }

        throw new FcmErreur('Envoi FCM refusé : HTTP '.$reponse->status().' ('.preg_replace('/[^A-Z_]/', '', strtoupper($code)).').');
    }

    private function urlEnvoi(): string
    {
        return 'https://fcm.googleapis.com/v1/projects/'.rawurlencode($this->identifiants->projet).'/messages:send';
    }

    /** @param  array<string, mixed>  $corps */
    private function post(string $url, array $corps, string $jetonAcces): Response
    {
        try {
            return Http::withToken($jetonAcces)->acceptJson()->timeout(10)->post($url, $corps);
        } catch (ConnectionException) {
            throw new FcmErreur('FCM injoignable (délai dépassé ou erreur réseau).');
        }
    }

    private function jetonAcces(): string
    {
        $enCache = Cache::get(self::CLE_CACHE);
        if (is_string($enCache) && $enCache !== '') {
            return $enCache;
        }

        try {
            $reponse = Http::asForm()->acceptJson()->timeout(10)->post($this->identifiants->uriJeton, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->assertion(),
            ]);
        } catch (ConnectionException) {
            throw new FcmErreur('Authentification Google injoignable (délai dépassé ou erreur réseau).');
        }

        $jeton = $reponse->json('access_token');
        if (! $reponse->successful() || ! is_string($jeton) || $jeton === '') {
            $code = preg_replace('/[^a-z_]/', '', strtolower((string) $reponse->json('error')));
            throw new FcmErreur('Authentification Google refusée : HTTP '.$reponse->status().($code !== '' ? ' ('.$code.')' : '').'. Vérifier FCM_CREDENTIALS.');
        }

        Cache::put(self::CLE_CACHE, $jeton, max(60, (int) $reponse->json('expires_in', 3600) - 300));

        return $jeton;
    }

    private function assertion(): string
    {
        $maintenant = time();
        $entete = self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $charge = self::base64Url((string) json_encode([
            'iss' => $this->identifiants->emailCompte,
            'scope' => self::PORTEE,
            'aud' => $this->identifiants->uriJeton,
            'iat' => $maintenant,
            'exp' => $maintenant + 3600,
        ]));

        $signature = '';
        if (! @openssl_sign("$entete.$charge", $signature, $this->identifiants->clePrivee, OPENSSL_ALGO_SHA256)) {
            throw new FcmErreur('La clé privée de FCM_CREDENTIALS est inutilisable pour signer la demande.');
        }

        return "$entete.$charge.".self::base64Url($signature);
    }

    private static function base64Url(string $texte): string
    {
        return rtrim(strtr(base64_encode($texte), '+/', '-_'), '=');
    }
}
