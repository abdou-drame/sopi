<?php

namespace App\Http\Resources;

use App\Models\Prestataire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne de la liste des demandes (vue administrateur).
 *
 * @mixin Prestataire
 */
class DemandePrestataireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->user->nom,
            'prenom' => $this->user->prenom,
            'nomEntreprise' => $this->nom_entreprise,
            'metier' => $this->metier->value,
            'quartier' => $this->quartier,
            'statutCompte' => $this->user->statut->value,
            'dateSoumission' => $this->created_at?->toIso8601String(),
            'nombreJustificatifs' => $this->justificatifs_count,
        ];
    }
}
