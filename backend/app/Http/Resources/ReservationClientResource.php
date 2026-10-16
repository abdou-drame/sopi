<?php

namespace App\Http\Resources;

use App\Enums\StatutReservation;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Services\PaiementReservationService;
use App\Services\ReponseReservationService;
use App\Services\SuiviReservationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réservation vue par son client propriétaire (seul destinataire de codeReservation, null tant qu'il n'existe pas).
 * Aucune donnée personnelle du prestataire (ni e-mail, nom, prénom, identifiant utilisateur), sauf son téléphone une fois la
 * réservation confirmée, en cours ou terminée (null avant, et pour une réservation refusée ou annulée).
 *
 * @mixin Reservation
 */
class ReservationClientResource extends JsonResource
{
    public static $wrap = null;

    /** Paiement le plus récent (statut et acompte seulement : ni commission, ni référence, ni URL), ou null. */
    private function paiementVisible(): ?array
    {
        $paiement = $this->paiements->sortByDesc(fn ($p) => $p->created_at->getTimestamp().$p->id)->first();

        return $paiement === null ? null : [
            'statut' => $paiement->statut->value,
            'montantAcompte' => (float) $paiement->montant_acompte,
        ];
    }

    /** Téléphone du prestataire, communiqué au client seulement une fois la réservation confirmée (jamais avant, ni après annulation ou refus). */
    private function telephonePrestataire(Prestataire $prestataire): ?string
    {
        $visible = in_array($this->statut, [StatutReservation::Confirmee, StatutReservation::EnCours, StatutReservation::Terminee], true);

        return $visible ? $prestataire->user->telephone : null;
    }

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
            'peutPayer' => PaiementReservationService::peutPayer($this->resource),
            'paiementAvant' => PaiementReservationService::echeancePaiement($this->resource)?->toIso8601String(),
            'reponseAvant' => ReponseReservationService::echeanceReponse($this->resource)?->toIso8601String(),
            'paiement' => $this->paiementVisible(),
            'peutNoter' => $this->statut === StatutReservation::Terminee && $this->avis === null,
            'avis' => $this->avis === null ? null : new AvisClientResource($this->avis),
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
                'telephone' => $this->telephonePrestataire($prestataire),
            ],
        ];
    }
}
