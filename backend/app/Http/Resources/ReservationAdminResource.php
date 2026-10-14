<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use App\Services\ReservationsAdminService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réservation vue par l'administrateur. En liste, le client n'a que son prénom et l'initiale de son nom ; le détail donne le nom
 * complet, le paiement, l'avis, le litige et les dates clés. Le code de réservation n'est jamais renvoyé. Montants en FCFA entiers.
 * Relations à charger : client, creneau.prestataire, prestation, paiements (et avis, litiges pour le détail).
 *
 * @mixin Reservation
 */
class ReservationAdminResource extends JsonResource
{
    public static $wrap = null;

    private bool $detail = false;

    public static function detail(Reservation $reservation): self
    {
        $ressource = new self($reservation);
        $ressource->detail = true;

        return $ressource;
    }

    public function toArray(Request $request): array
    {
        $paiement = ReservationsAdminService::paiementRetenu($this->paiements);

        $commun = [
            'id' => $this->id,
            'statut' => $this->statut->value,
            'prestataire' => ['id' => $this->creneau?->prestataire_id, 'nomEntreprise' => $this->creneau?->prestataire?->nom_entreprise],
            'prestation' => ['id' => $this->prestation_id, 'nom' => $this->prestation?->nom],
            'creneau' => [
                'id' => $this->creneau_id,
                'debut' => $this->creneau?->date_debut->toIso8601String(),
                'fin' => $this->creneau?->date_fin->toIso8601String(),
            ],
            'prixApplique' => PaiementAdminResource::montant($this->prix_applique),
        ];

        if (! $this->detail) {
            return [
                'id' => $commun['id'],
                'statut' => $commun['statut'],
                'client' => [
                    'id' => $this->client_id,
                    'prenom' => $this->client?->prenom,
                    'initialeNom' => $this->client === null ? null : mb_strtoupper(mb_substr($this->client->nom, 0, 1)).'.',
                ],
            ] + array_slice($commun, 2) + [
                'statutPaiement' => $paiement?->statut->value,
                'dateCreation' => $this->created_at->toIso8601String(),
            ];
        }

        $avis = $this->avis;
        $litige = $this->litiges->first();

        return array_merge($commun, [
            'client' => ['id' => $this->client_id, 'prenom' => $this->client?->prenom, 'nom' => $this->client?->nom],
            'prestation' => $commun['prestation'] + ['dureeMinutes' => $this->prestation?->duree_minutes],
            'motifRefus' => $this->motif_refus,
            'paiement' => $paiement === null ? null : ['id' => $paiement->id] + PaiementAdminResource::resume($paiement),
            'nombrePaiements' => $this->paiements->count(),
            'avis' => $avis === null ? null : ['id' => $avis->id, 'note' => $avis->note, 'statut' => $avis->statut->value, 'dateCreation' => $avis->created_at->toIso8601String()],
            'litige' => $litige === null ? null : ['id' => $litige->id, 'statut' => $litige->statut->value, 'dateCreation' => $litige->created_at->toIso8601String()],
            'historique' => [
                'creeeLe' => $this->created_at->toIso8601String(),
                'accepteeLe' => $this->acceptee_le?->toIso8601String(),
                'payeLe' => $paiement?->paye_le?->toIso8601String(),
                'remboursementDuLe' => $paiement?->remboursement_du_le?->toIso8601String(),
                'rembourseLe' => $paiement?->rembourse_le?->toIso8601String(),
                'debutCreneau' => $this->creneau?->date_debut->toIso8601String(),
                'finCreneau' => $this->creneau?->date_fin->toIso8601String(),
                'misAJourLe' => $this->updated_at->toIso8601String(),
            ],
        ]);
    }
}
