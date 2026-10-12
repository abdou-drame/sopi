<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Demande de réservation vue par le prestataire. Le client n'est identifié que par son prénom et l'initiale de son nom :
 * ni téléphone, ni e-mail, ni identifiant, ni code de réservation.
 *
 * @mixin Reservation
 */
class DemandeReservationResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statut' => $this->statut->value,
            'dateCreation' => $this->created_at->toIso8601String(),
            'motifRefus' => $this->motif_refus,
            'creneau' => [
                'id' => $this->creneau->id,
                'dateDebut' => $this->creneau->date_debut->toIso8601String(),
                'dateFin' => $this->creneau->date_fin->toIso8601String(),
            ],
            'prestation' => [
                'id' => $this->prestation->id,
                'nom' => $this->prestation->nom,
                'dureeMinutes' => $this->prestation->duree_minutes,
                'prix' => (float) $this->prestation->prix,
            ],
            'client' => [
                'prenom' => $this->client->prenom,
                'initialeNom' => mb_strtoupper(mb_substr(trim($this->client->nom), 0, 1)),
            ],
        ];
    }
}
