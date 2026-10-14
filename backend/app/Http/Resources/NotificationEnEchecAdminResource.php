<?php

namespace App\Http\Resources;

use App\Enums\StatutEnvoi;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notification en échec vue par l'administrateur. Jamais le titre ni le corps du message, ni l'adresse e-mail ou le jeton push du
 * destinataire : seulement son id et son rôle. Le destinataire (id, role) doit être chargé.
 *
 * @mixin Notification
 */
class NotificationEnEchecAdminResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $canaux = [];
        if ($this->canal_email_statut === StatutEnvoi::Echoue) {
            $canaux[] = 'email';
        }
        if ($this->canal_push_statut === StatutEnvoi::Echoue) {
            $canaux[] = 'push';
        }

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'destinataire' => ['id' => $this->user_id, 'role' => $this->user?->role->value],
            'canauxEnEchec' => $canaux,
            'tentatives' => $this->tentatives,
            'classeErreur' => $this->dernier_echec_classe,
            'dateCreation' => $this->created_at->toIso8601String(),
        ];
    }
}
