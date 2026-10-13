<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Pilote Laravel Mail. Il utilise le transport choisi par MAIL_MAILER (smtp en production) et les variables MAIL_* du serveur :
 * aucune clé dans le dépôt. Texte brut, sans lien ni secret.
 */
class SmtpEnvoiEmail implements EnvoiEmail
{
    public function envoyer(Notification $notification, User $destinataire): void
    {
        $texte = "Bonjour {$destinataire->prenom},\n\n{$notification->corps}\n\nL'équipe Sopi";

        Mail::raw($texte, function ($message) use ($destinataire, $notification) {
            $message->to($destinataire->email)->subject($notification->titre);
        });
    }
}
