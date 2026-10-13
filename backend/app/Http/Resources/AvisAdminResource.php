<?php

namespace App\Http\Resources;

use App\Models\Avis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Avis vu par l'administrateur, avec son signalement et sa modération. Le client et le prestataire doivent être chargés.
 *
 * @mixin Avis
 */
class AvisAdminResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'commentaire' => $this->commentaire,
            'dateCreation' => $this->created_at->toIso8601String(),
            'reservationId' => $this->reservation_id,
            'client' => ['id' => $this->client_id, 'nom' => $this->client?->nom, 'prenom' => $this->client?->prenom],
            'prestataire' => ['id' => $this->prestataire_id, 'nomEntreprise' => $this->prestataire?->nom_entreprise],
            'statut' => $this->statut->value,
            'signaleLe' => $this->signale_le?->toIso8601String(),
            'motifSignalement' => $this->motif_signalement,
            'modereLe' => $this->modere_le?->toIso8601String(),
            'moderePar' => $this->modere_par_id,
            'motifModeration' => $this->motif_moderation,
            'masque' => $this->masque_le !== null,
            'masqueLe' => $this->masque_le?->toIso8601String(),
        ];
    }
}
