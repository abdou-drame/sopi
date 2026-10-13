<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;

/** Envoi d'une notification par e-mail. Lève une exception en cas d'échec : l'appelant gère les essais. */
interface EnvoiEmail
{
    public function envoyer(Notification $notification, User $destinataire): void;
}
