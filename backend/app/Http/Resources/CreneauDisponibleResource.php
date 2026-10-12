<?php

namespace App\Http\Resources;

use App\Models\Creneau;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Créneau réservable vu par un client : ni statut, ni donnée du prestataire ou d'autrui.
 *
 * @mixin Creneau
 */
class CreneauDisponibleResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dateDebut' => $this->date_debut->toIso8601String(),
            'dateFin' => $this->date_fin->toIso8601String(),
        ];
    }
}
