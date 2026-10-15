<?php

namespace App\Services;

use App\Enums\StatutRetrait;
use App\Models\Retrait;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Consultation des retraits par l'administrateur (M12.2) : lecture seule. « Bloqué » a la même définition que l'alerte
 * `retraits_bloques` : EN_COURS depuis plus longtemps que `sopi.retraits.delai_blocage_minutes`. La référence DexPay n'est lue que
 * pour le détail.
 */
class RetraitsAdminService
{
    public const PAR_PAGE = 20;

    private const COLONNES = ['id', 'prestataire_id', 'montant', 'statut', 'operateur', 'telephone_versement', 'traite_le', 'recredite_le', 'created_at', 'updated_at'];

    /**
     * @param  array{statut?: ?string, prestataire?: ?string, bloques?: ?bool}  $filtres
     */
    public function lister(array $filtres): LengthAwarePaginator
    {
        $requete = Retrait::query()->select(self::COLONNES)->with('prestataire:id,user_id,nom_entreprise');

        if (filled($filtres['statut'] ?? null)) {
            $requete->where('statut', $filtres['statut']);
        }
        if (filled($filtres['prestataire'] ?? null)) {
            $requete->where('prestataire_id', $filtres['prestataire']);
        }
        if (($filtres['bloques'] ?? null) === true) {
            $requete->where('statut', StatutRetrait::EnCours->value)->where('created_at', '<=', self::limiteBlocage());
        } elseif (($filtres['bloques'] ?? null) === false) {
            $requete->where(function (Builder $q) {
                $q->where('statut', '!=', StatutRetrait::EnCours->value)->orWhere('created_at', '>', self::limiteBlocage());
            });
        }

        return $requete->orderByDesc('created_at')->orderByDesc('id')->paginate(self::PAR_PAGE);
    }

    public function trouver(string $id): ?Retrait
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return Retrait::query()
            ->select([...self::COLONNES, 'reference_dexpay'])
            ->with('prestataire:id,user_id,nom_entreprise')
            ->find($id);
    }

    /** Un retrait EN_COURS créé à cet instant ou avant est bloqué. */
    public static function limiteBlocage(): Carbon
    {
        return now()->subMinutes((int) config('sopi.retraits.delai_blocage_minutes'));
    }
}
