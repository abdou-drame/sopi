<?php

namespace App\Services\Notification;

use App\Exceptions\ConfigurationNotificationInvalide;
use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Pilote Laravel Mail en SMTP. Le transport et les accès viennent des variables MAIL_* du serveur : aucune clé dans le dépôt.
 * La configuration est vérifiée avant chaque envoi ; un manque lève une erreur qui nomme les variables, jamais leurs valeurs.
 */
class SmtpEnvoiEmail implements EnvoiEmail
{
    /** Variables obligatoires : nom → valeur actuelle. */
    private const VARIABLES = [
        'MAIL_MAILER' => 'mail.default',
        'MAIL_HOST' => 'mail.mailers.smtp.host',
        'MAIL_PORT' => 'mail.mailers.smtp.port',
        'MAIL_USERNAME' => 'mail.mailers.smtp.username',
        'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
        'MAIL_FROM_ADDRESS' => 'mail.from.address',
    ];

    /** @throws ConfigurationNotificationInvalide */
    public static function verifierConfiguration(): void
    {
        $manquantes = [];
        foreach (self::VARIABLES as $nom => $cle) {
            if (blank(config($cle))) {
                $manquantes[] = $nom;
            }
        }
        if ($manquantes === [] && config('mail.default') !== 'smtp') {
            throw new ConfigurationNotificationInvalide('L\'envoi d\'e-mails exige MAIL_MAILER=smtp.');
        }
        if ($manquantes === [] && config('mail.from.address') === 'hello@example.com') {
            $manquantes[] = 'MAIL_FROM_ADDRESS';
        }
        if ($manquantes !== []) {
            throw new ConfigurationNotificationInvalide('Configuration e-mail incomplète. Variables à renseigner : '.implode(', ', $manquantes).'.');
        }
    }

    public function envoyer(Notification $notification, User $destinataire): void
    {
        self::verifierConfiguration();

        Mail::to($destinataire->email)->send(new NotificationMail($notification, $destinataire));
    }
}
