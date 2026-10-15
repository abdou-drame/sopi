<?php

namespace App\Mail;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail d'une notification : HTML bleu et blanc + version texte, en français. Jamais de code de réservation ni de téléphone. */
class NotificationMail extends Mailable
{
    public function __construct(public Notification $notification, public User $destinataire) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->titre);
    }

    public function content(): Content
    {
        $donnees = [
            'prenom' => $this->destinataire->prenom,
            'titre' => $this->notification->titre,
            'corps' => $this->notification->corps,
            'accroche' => $this->notification->type->accrocheEmail(),
        ];

        return new Content(view: 'emails.notification', text: 'emails.notification-texte', with: $donnees);
    }
}
