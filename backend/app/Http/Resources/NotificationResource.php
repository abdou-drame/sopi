<?php

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notification vue par son destinataire. N'expose AUCUN champ technique d'envoi (statuts des canaux, tentatives, prochain essai,
 * classe d'erreur) ni l'identifiant de l'utilisateur.
 *
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'titre' => $this->titre,
            'corps' => $this->corps,
            'donnees' => $this->donnees ?? (object) [],
            'lue' => $this->lue_le !== null,
            'lueLe' => $this->lue_le?->toIso8601String(),
            'dateCreation' => $this->created_at->toIso8601String(),
        ];
    }
}
