<?php

namespace App\Services\Notification;

use App\Services\Paiement\DexPay\JournalDexPay;
use Throwable;

/**
 * Message d'erreur d'envoi (e-mail, push) rendu sûr pour la base et le back-office : 300 caractères au plus, sans adresse
 * e-mail, clé, jeton, mot de passe ni numéro. S'appuie sur les règles de JournalDexPay et masque en plus les accès configurés.
 */
final class MasqueErreur
{
    private const MASQUE = '[masqué]';

    public static function message(Throwable $e): string
    {
        $texte = class_basename($e).' : '.$e->getMessage();

        // Valeurs réellement configurées (accès SMTP, identifiants FCM).
        foreach ([config('mail.mailers.smtp.username'), config('mail.mailers.smtp.password')] as $secret) {
            if (is_string($secret) && strlen(trim($secret)) >= 4) {
                $texte = str_replace(trim($secret), self::MASQUE, $texte);
            }
        }
        // Blocs de clé privée et adresses e-mail.
        $texte = (string) preg_replace('/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?(-----END [A-Z ]*PRIVATE KEY-----|$)/s', self::MASQUE, $texte);
        $texte = (string) preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+/', '[adresse masquée]', $texte);

        return JournalDexPay::extrait($texte);
    }
}
