<?php

namespace App\Http\Resources;

use App\Models\Sanction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sanction dans la liste générale de l'administrateur (M12.1) : le compte sanctionné doit être chargé (id, prénom, nom, rôle).
 *
 * @mixin Sanction
 */
class SanctionAdminResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'utilisateur' => [
                'id' => $this->user_id,
                'prenom' => $this->user?->prenom,
                'nom' => $this->user?->nom,
                'role' => $this->user?->role->value,
            ],
            'motif' => $this->motif,
            'litigeId' => $this->litige_id,
            'avisId' => $this->avis_id,
            'dateCreation' => $this->created_at->toIso8601String(),
            'dateFin' => $this->date_fin?->toIso8601String(),
            'active' => $this->levee_le === null,
            'leveeLe' => $this->levee_le?->toIso8601String(),
        ];
    }
}
