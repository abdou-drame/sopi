<?php

namespace App\Services;

use App\Enums\TypeSanction;
use App\Models\Sanction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Liste générale des sanctions pour l'administrateur (M12.1), lecture seule, du plus récent au plus ancien. */
class SanctionsAdminService
{
    public const PAR_PAGE = 20;

    /**
     * @param  array{actives?: ?bool, type?: ?string, utilisateur?: ?string, echeanceDansHeures?: ?int}  $filtres
     */
    public function lister(array $filtres): LengthAwarePaginator
    {
        $requete = Sanction::query()->with('user:id,prenom,nom,role');

        if (($filtres['actives'] ?? null) !== null) {
            $filtres['actives'] ? $requete->whereNull('levee_le') : $requete->whereNotNull('levee_le');
        }
        if (filled($filtres['type'] ?? null)) {
            $requete->where('type', $filtres['type']);
        }
        if (filled($filtres['utilisateur'] ?? null)) {
            $requete->where('user_id', $filtres['utilisateur']);
        }
        if (filled($filtres['echeanceDansHeures'] ?? null)) {
            // Même définition que l'alerte « suspensions à échéance » : suspensions non levées qui se terminent dans l'intervalle.
            $maintenant = now();
            $requete->where('type', TypeSanction::SuspensionTemporaire->value)
                ->whereNull('levee_le')
                ->where('date_fin', '>', $maintenant)
                ->where('date_fin', '<=', $maintenant->copy()->addHours((int) $filtres['echeanceDansHeures']));
        }

        return $requete->orderByDesc('created_at')->orderByDesc('id')->paginate(self::PAR_PAGE);
    }
}
