<?php

namespace App\Http\Resources;

use App\Models\Creneau;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Créneau (sorties en camelCase). Dates au format ISO 8601 avec décalage horaire.
 *
 * @mixin Creneau
 */
class CreneauResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dateDebut' => $this->date_debut->toIso8601String(),
            'dateFin' => $this->date_fin->toIso8601String(),
            'statut' => $this->statut->value,
        ];
    }
}
