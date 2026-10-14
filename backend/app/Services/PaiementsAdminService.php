<?php

namespace App\Services;

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Consultation des paiements par l'administrateur (M12.2) : lecture seule. La référence DexPay n'est lue que pour le détail ;
 * l'URL de paiement n'est jamais renvoyée. « Remboursement dû » a la même définition que les alertes du tableau de bord :
 * paiement reçu (réussi ou échoué) marqué à rembourser.
 */
class PaiementsAdminService
{
    public const PAR_PAGE = 20;

    private const COLONNES = ['id', 'reservation_id', 'statut', 'montant_acompte', 'commission', 'paye_le', 'rembourse_le', 'remboursement_du_le', 'remboursement_tentatives', 'credite_le', 'created_at', 'updated_at'];

    /**
     * @param  array{statut?: ?string, remboursementDu?: ?bool, retardRemboursementMinutes?: ?int, reservation?: ?string}  $filtres
     */
    public function lister(array $filtres): LengthAwarePaginator
    {
        $requete = Paiement::query()->select(self::COLONNES);

        if (filled($filtres['statut'] ?? null)) {
            $requete->where('statut', $filtres['statut']);
        }
        if (filled($filtres['reservation'] ?? null)) {
            $requete->where('reservation_id', $filtres['reservation']);
        }
        if (($filtres['remboursementDu'] ?? null) === true) {
            $this->dus($requete);
        } elseif (($filtres['remboursementDu'] ?? null) === false) {
            $requete->where(function (Builder $q) {
                $q->whereNotIn('statut', self::statutsRecus())->orWhereNull('remboursement_du_le');
            });
        }
        if (filled($filtres['retardRemboursementMinutes'] ?? null)) {
            // Dus depuis au moins N minutes, essais épuisés compris : réunit les alertes « en retard » et « en échec ».
            $this->dus($requete)->where('remboursement_du_le', '<=', now()->subMinutes((int) $filtres['retardRemboursementMinutes']));
        }

        return $requete->orderByDesc('created_at')->orderByDesc('id')->paginate(self::PAR_PAGE);
    }

    public function trouver(string $id): ?Paiement
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return Paiement::query()
            ->select([...self::COLONNES, 'reference_dexpay'])
            ->with([
                'reservation:id,client_id,creneau_id,prix_applique,statut',
                'reservation.creneau:id,prestataire_id',
            ])
            ->find($id);
    }

    /** @return list<string> */
    public static function statutsRecus(): array
    {
        return [StatutPaiement::Reussi->value, StatutPaiement::Echoue->value];
    }

    private function dus(Builder $requete): Builder
    {
        return $requete->whereIn('statut', self::statutsRecus())->whereNotNull('remboursement_du_le');
    }
}
