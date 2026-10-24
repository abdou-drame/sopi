<?php

namespace App\Http\Resources;

use App\Enums\StatutCompte;
use App\Enums\StatutJustificatif;
use App\Models\Prestataire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'une demande (vue administrateur) : coordonnées et justificatifs, jamais de chemin de stockage.
 *
 * @mixin Prestataire
 */
class DemandePrestataireDetailResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $statut = $this->user->statut;

        return [
            'id' => $this->id,
            'nom' => $this->user->nom,
            'prenom' => $this->user->prenom,
            'telephone' => $this->user->telephone,
            'email' => $this->user->email,
            'nomEntreprise' => $this->nom_entreprise,
            'metier' => $this->metier,
            'quartier' => $this->quartier,
            'adresse' => $this->adresse,
            'statutCompte' => $statut->value,
            'motifRefus' => $statut === StatutCompte::Refuse ? $this->motif_refus : null,
            'dateSoumission' => $this->created_at?->toIso8601String(),
            'justificatifs' => $this->justificatifs->map(fn ($justificatif) => [
                'id' => $justificatif->id,
                'typeDocument' => $justificatif->type_document->value,
                'statut' => $justificatif->statut->value,
                'motifRejet' => $justificatif->statut === StatutJustificatif::Rejete ? $justificatif->motif_rejet : null,
                'nomOriginal' => $justificatif->nom_original,
                'typeMime' => $justificatif->type_mime,
                'taille' => $justificatif->taille,
            ])->values(),
        ];
    }
}
