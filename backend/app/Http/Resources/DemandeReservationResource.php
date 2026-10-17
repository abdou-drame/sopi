<?php

namespace App\Http\Resources;

use App\Enums\StatutPaiement;
use App\Models\Reservation;
use App\Services\Paiement\CalculPaiement;
use App\Services\ReponseReservationService;
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

    /** Attribut de requête posé par la liste et le détail : les montants ne sont pas rendus dans les réponses d'action (annulation, etc.). */
    public const AVEC_MONTANTS = 'demande_avec_montants';

    /**
     * Acompte et commission de la réservation : ceux du paiement le plus récent non échoué s'il existe (montants figés à
     * l'ouverture du paiement), sinon calculés par le serveur sur le prix, avec les mêmes taux et la même règle.
     *
     * @return array{0: string, 1: string}
     */
    private function montants(): array
    {
        $paiement = $this->paiements
            ->reject(fn ($p) => $p->statut === StatutPaiement::Echoue)
            ->sortByDesc(fn ($p) => $p->created_at->getTimestamp().$p->id)
            ->first();

        if ($paiement !== null) {
            return [(string) $paiement->montant_acompte, (string) $paiement->commission];
        }

        $montants = CalculPaiement::depuisConfiguration()->calculer((string) $this->prix_applique);

        return [$montants->montantAcompte, $montants->commission];
    }

    public function toArray(Request $request): array
    {
        $montants = [];
        if ($request->attributes->get(self::AVEC_MONTANTS, false)) {
            [$acompte, $commission] = $this->montants();
            $montants = [
                'acompte' => (float) $acompte,
                'commission' => (float) $commission,
                'montantCredite' => (float) CalculPaiement::montantCredite($acompte, $commission),
            ];
        }

        return [
            'id' => $this->id,
            'statut' => $this->statut->value,
            'dateCreation' => $this->created_at->toIso8601String(),
            'motifRefus' => $this->motif_refus,
            'reponseAvant' => ReponseReservationService::echeanceReponse($this->resource)?->toIso8601String(),
            ...$montants,
            'creneau' => [
                'id' => $this->creneau->id,
                'dateDebut' => $this->creneau->date_debut->toIso8601String(),
                'dateFin' => $this->creneau->date_fin->toIso8601String(),
            ],
            'prestation' => [
                'id' => $this->prestation->id,
                'nom' => $this->prestation->nom,
                'dureeMinutes' => $this->prestation->duree_minutes,
                'prix' => (float) $this->prix_applique,
            ],
            'client' => [
                'prenom' => $this->client->prenom,
                'initialeNom' => mb_strtoupper(mb_substr(trim($this->client->nom), 0, 1)),
            ],
        ];
    }
}
