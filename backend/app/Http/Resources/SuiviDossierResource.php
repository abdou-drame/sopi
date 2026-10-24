<?php

namespace App\Http\Resources;

use App\Enums\StatutCompte;
use App\Enums\StatutJustificatif;
use App\Models\Prestataire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Suivi du dossier par le prestataire. Aucune donnée personnelle, aucun identifiant interne, aucun chemin de fichier.
 *
 * @mixin Prestataire
 */
class SuiviDossierResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $statut = $this->user->statut;

        return [
            'nomEntreprise' => $this->nom_entreprise,
            'metier' => $this->metier,
            'statutCompte' => $statut->value,
            'motifRefus' => $statut === StatutCompte::Refuse ? $this->motif_refus : null,
            'dateSoumission' => $this->created_at?->toIso8601String(),
            'justificatifs' => $this->justificatifs->sortBy('date_soumission')->map(fn ($justificatif) => [
                'typeDocument' => $justificatif->type_document->value,
                'statut' => $justificatif->statut->value,
                'motifRejet' => $justificatif->statut === StatutJustificatif::Rejete ? $justificatif->motif_rejet : null,
            ])->values(),
        ];
    }
}
