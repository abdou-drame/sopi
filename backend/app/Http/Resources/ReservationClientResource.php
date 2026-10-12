<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use App\Services\SuiviReservationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réservation vue par son client propriétaire (seul destinataire de codeReservation, null tant qu'il n'existe pas).
 * Aucune donnée personnelle du prestataire (ni téléphone, e-mail, nom, prénom, identifiant utilisateur).
 *
 * @mixin Reservation
 */
class ReservationClientResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $prestation = $this->prestation;
        $prestataire = $prestation->prestataire;

        return [
            'id' => $this->id,
            'statut' => $this->statut->value,
            'dateCreation' => $this->created_at->toIso8601String(),
            'motifRefus' => $this->motif_refus,
            'codeReservation' => $this->code_reservation,
            'peutAnnuler' => SuiviReservationService::peutAnnuler($this->resource),
            'creneau' => [
                'id' => $this->creneau->id,
                'dateDebut' => $this->creneau->date_debut->toIso8601String(),
                'dateFin' => $this->creneau->date_fin->toIso8601String(),
            ],
            'prestation' => [
                'id' => $prestation->id,
                'nom' => $prestation->nom,
                'dureeMinutes' => $prestation->duree_minutes,
                'prix' => (float) $prestation->prix,
            ],
            'prestataire' => [
                'id' => $prestataire->id,
                'nomEntreprise' => $prestataire->nom_entreprise,
                'quartier' => $prestataire->quartier,
                'adresse' => $prestataire->adresse,
            ],
        ];
    }
}
