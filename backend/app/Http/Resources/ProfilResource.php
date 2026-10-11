<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class ProfilResource extends JsonResource
{
    public static $wrap = null;

    // Jamais le mot de passe ni le jeton push.
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'role' => $this->role->value,
            'statutCompte' => $this->statut->value,
            'dateCreation' => $this->created_at?->toIso8601String(),
        ];
    }
}
