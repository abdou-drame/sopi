<?php

namespace App\Http\Resources;

use App\Models\Avis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Avis vu par le client qui l'a donné (sur sa réservation) : note, commentaire et date seulement.
 *
 * @mixin Avis
 */
class AvisClientResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'note' => $this->note,
            'commentaire' => $this->commentaire,
            'dateCreation' => $this->created_at->toIso8601String(),
            // Indicateur neutre : un avis masqué reste visible de son auteur mais n'est plus affiché publiquement (sans motif).
            'affichePubliquement' => $this->masque_le === null,
        ];
    }
}
