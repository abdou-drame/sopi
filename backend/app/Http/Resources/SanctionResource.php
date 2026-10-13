<?php

namespace App\Http\Resources;

use App\Models\Sanction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sanction vue par l'administrateur. `leveePar` est vide pour une sanction non levée ou levée automatiquement à l'échéance.
 *
 * @mixin Sanction
 */
class SanctionResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'utilisateurId' => $this->user_id,
            'type' => $this->type->value,
            'motif' => $this->motif,
            'dateFin' => $this->date_fin?->toIso8601String(),
            'litigeId' => $this->litige_id,
            'avisId' => $this->avis_id,
            'posePar' => $this->pose_par_id,
            'dateCreation' => $this->created_at->toIso8601String(),
            'active' => $this->levee_le === null,
            'leveeLe' => $this->levee_le?->toIso8601String(),
            'leveePar' => $this->levee_par_id,
        ];
    }
}
