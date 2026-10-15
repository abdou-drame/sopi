<?php

namespace App\Http\Resources;

use App\Models\Metier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Métier vu par l'administrateur : code (valeur stockée chez les prestataires), libellé, état et nombre de prestataires rattachés.
 *
 * @mixin Metier
 */
class MetierAdminResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'actif' => $this->actif,
            'nombrePrestataires' => (int) ($this->nombre_prestataires ?? 0),
            'dateCreation' => $this->created_at?->toIso8601String(),
        ];
    }
}
