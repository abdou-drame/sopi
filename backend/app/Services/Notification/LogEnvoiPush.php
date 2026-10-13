<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/** Pilote par défaut : n'envoie rien, journalise l'identifiant seulement (ni jeton, ni texte). */
class LogEnvoiPush implements EnvoiPush
{
    public function envoyer(Notification $notification, User $destinataire): void
    {
        Log::info('Notification push traitée (pilote log).', ['notification' => $notification->id, 'type' => $notification->type->value]);
    }
}
