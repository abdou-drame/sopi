<?php

namespace App\Http\Resources;

use App\Models\Prestation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prestation d'un prestataire (sorties en camelCase). Le prix est un nombre en FCFA.
 *
 * @mixin Prestation
 */
class PrestationResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'domaine' => $this->domaine,
            'dureeMinutes' => $this->duree_minutes,
            'prix' => (float) $this->prix,
            'actif' => $this->actif,
        ];
    }
}
