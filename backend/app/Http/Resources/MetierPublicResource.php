<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Métier tel que l'application le présente (aucune icône n'est stockée aujourd'hui : l'application associe une icône au code). */
class MetierPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'nom' => $this->libelle,
        ];
    }
}
