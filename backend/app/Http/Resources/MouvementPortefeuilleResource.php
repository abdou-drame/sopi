<?php

namespace App\Http\Resources;

use App\Models\MouvementPortefeuille;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mouvement du solde vu par son prestataire. Le montant est positif ; le sens se lit dans « type ».
 *
 * @mixin MouvementPortefeuille
 */
class MouvementPortefeuilleResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'montant' => (float) $this->montant,
            'libelle' => $this->libelle,
            'soldeApres' => (float) $this->solde_apres,
            'reservationId' => $this->reservation_id,
            'retraitId' => $this->retrait_id,
            'date' => $this->created_at->toIso8601String(),
        ];
    }
}
