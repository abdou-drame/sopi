<?php

namespace App\Http\Resources;

use App\Models\Paiement;
use App\Services\PaiementsAdminService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Paiement vu par l'administrateur. En liste : montants, statut, remboursement dû et dates. Le détail ajoute la réservation, la
 * date de crédit du prestataire et la référence DexPay (seul endroit où elle apparaît). Jamais l'URL de paiement. Montants en
 * FCFA entiers.
 *
 * @mixin Paiement
 */
class PaiementAdminResource extends JsonResource
{
    public static $wrap = null;

    private bool $detail = false;

    public static function detail(Paiement $paiement): self
    {
        $ressource = new self($paiement);
        $ressource->detail = true;

        return $ressource;
    }

    public function toArray(Request $request): array
    {
        $ligne = ['id' => $this->id, 'reservationId' => $this->reservation_id] + self::resume($this->resource);

        if (! $this->detail) {
            return $ligne;
        }

        return $ligne + [
            'reservation' => [
                'id' => $this->reservation_id,
                'statut' => $this->reservation?->statut->value,
                'prixApplique' => $this->reservation === null ? null : self::montant($this->reservation->prix_applique),
                'clientId' => $this->reservation?->client_id,
                'prestataireId' => $this->reservation?->creneau?->prestataire_id,
            ],
            'creeLe' => $this->created_at->toIso8601String(),
            'tentativesMaximum' => (int) config('sopi.paiement.remboursement_max_tentatives'),
            'referenceDexpay' => $this->reference_dexpay,
        ];
    }

    /**
     * Les champs communs au paiement seul et au paiement résumé dans une réservation (jamais de référence ni d'URL).
     *
     * @return array<string, mixed>
     */
    public static function resume(Paiement $paiement): array
    {
        $du = $paiement->remboursement_du_le !== null && in_array($paiement->statut->value, PaiementsAdminService::statutsRecus(), true);

        return [
            'statut' => $paiement->statut->value,
            'montantAcompte' => self::montant($paiement->montant_acompte),
            'commission' => self::montant($paiement->commission),
            'remboursementDu' => $du,
            'remboursementDuLe' => $paiement->remboursement_du_le?->toIso8601String(),
            'tentativesRemboursement' => (int) $paiement->remboursement_tentatives,
            'tentativesEpuisees' => $du && (int) $paiement->remboursement_tentatives >= (int) config('sopi.paiement.remboursement_max_tentatives'),
            'payeLe' => $paiement->paye_le?->toIso8601String(),
            'rembourseLe' => $paiement->rembourse_le?->toIso8601String(),
            'crediteLe' => $paiement->credite_le?->toIso8601String(),
        ];
    }

    public static function montant(mixed $valeur): int
    {
        return (int) round((float) $valeur);
    }
}
