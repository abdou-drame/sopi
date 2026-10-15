<?php

namespace App\Services\Notification\Fcm;

use App\Exceptions\ConfigurationNotificationInvalide;

/**
 * Compte de service Firebase lu depuis FCM_CREDENTIALS (contenu JSON, son équivalent base64, ou chemin d'un fichier) et
 * FCM_PROJECT_ID (déduit du JSON sinon). Les messages d'erreur nomment les variables ou champs, jamais leur contenu.
 */
final class IdentifiantsFcm
{
    private const URI_JETON_PAR_DEFAUT = 'https://oauth2.googleapis.com/token';

    private function __construct(
        public readonly string $projet,
        public readonly string $emailCompte,
        public readonly string $clePrivee,
        public readonly string $uriJeton,
    ) {}

    /** @throws ConfigurationNotificationInvalide */
    public static function depuisConfiguration(): self
    {
        $brut = trim((string) config('sopi.notifications.fcm.credentials'));
        if ($brut === '') {
            throw new ConfigurationNotificationInvalide('Configuration push incomplète. Variables à renseigner : FCM_CREDENTIALS.');
        }

        $donnees = self::decoder($brut);
        if ($donnees === null) {
            throw new ConfigurationNotificationInvalide('FCM_CREDENTIALS est illisible : fournir le JSON du compte de service, son équivalent base64 ou le chemin du fichier.');
        }

        $projet = trim((string) config('sopi.notifications.fcm.project_id')) ?: (string) ($donnees['project_id'] ?? '');
        $manquants = [];
        foreach (['client_email', 'private_key'] as $champ) {
            if (blank($donnees[$champ] ?? null)) {
                $manquants[] = $champ;
            }
        }
        if ($manquants !== []) {
            throw new ConfigurationNotificationInvalide('FCM_CREDENTIALS incomplet. Champs absents du JSON : '.implode(', ', $manquants).'.');
        }
        if ($projet === '') {
            throw new ConfigurationNotificationInvalide('Configuration push incomplète. Variables à renseigner : FCM_PROJECT_ID (absent de FCM_CREDENTIALS).');
        }

        return new self($projet, (string) $donnees['client_email'], str_replace('\n', "\n", (string) $donnees['private_key']), (string) ($donnees['token_uri'] ?? self::URI_JETON_PAR_DEFAUT));
    }

    /** @return array<string, mixed>|null */
    private static function decoder(string $brut): ?array
    {
        $candidats = [$brut];
        if (! str_starts_with($brut, '{')) {
            $decode = base64_decode($brut, true);
            if ($decode !== false) {
                $candidats[] = $decode;
            }
            if (strlen($brut) < 1024 && is_file($brut) && is_readable($brut)) {
                $candidats[] = (string) file_get_contents($brut);
            }
        }

        foreach ($candidats as $texte) {
            $json = json_decode(trim($texte), true);
            if (is_array($json)) {
                return $json;
            }
        }

        return null;
    }
}
