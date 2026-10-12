<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réservation vue par son client. Aucune donnée personnelle du prestataire (ni téléphone, e-mail, nom, identifiant
 * utilisateur) et pas de code de réservation (généré au paiement).
 *
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
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
            'creneau' => [
                'id' => $this->creneau->id,
                'dateDebut' => $this->creneau->date_debut->toIso8601String(),
                'dateFin' => $this->creneau->date_fin->toIso8601String(),
            ],
            'prestation' => [
                'id' => $prestation->id,
                'nom' => $prestation->nom,
                'dureeMinutes' => $prestation->duree_minutes,
                'prix' => (float) $this->prix_applique,
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
