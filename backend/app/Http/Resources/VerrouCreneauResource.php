<?php

namespace App\Http\Resources;

use App\Models\Creneau;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Verrou temporaire d'un créneau, vu par celui qui le détient : aucune donnée personnelle.
 *
 * @mixin Creneau
 */
class VerrouCreneauResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'creneauId' => $this->id,
            'verrouExpireA' => $this->verrou_expire_a->toIso8601String(),
        ];
    }
}
