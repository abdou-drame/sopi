<?php

namespace App\Services;

use App\Enums\StatutCreneau;
use App\Models\Prestataire;
use Illuminate\Support\Carbon;

/**
 * Indisponibilité d'un prestataire (RF12, partie créneaux, RG26) : blocage immédiat des créneaux libres d'une plage.
 * Les réservations déjà enregistrées ne sont pas traitées ici (annulation, remboursement, notification : M5, M6, M10).
 */
class IndisponibiliteService
{
    public const DUREE_MAX_JOURS = 90;

    /**
     * Bloque en UNE requête conditionnelle tous les créneaux libres (au sens de Creneau::estLibre()) qui chevauchent la plage.
     * Idempotent. Les créneaux réservés ou verrouillés avec un verrou valide ne sont pas modifiés, seulement comptés.
     *
     * @return array{bloques: int, reserves: int, verrouilles: int}
     */
    public function declarer(Prestataire $prestataire, Carbon $debut, Carbon $fin): array
    {
        $dansLaPlage = fn () => $prestataire->creneaux()
            ->where('date_debut', '<', $fin)
            ->where('date_fin', '>', $debut);

        $bloques = $dansLaPlage()->libres()->update([
            'statut' => StatutCreneau::Indisponible->value,
            'verrou_expire_a' => null,
        ]);

        return [
            'bloques' => $bloques,
            'reserves' => $dansLaPlage()->where('statut', StatutCreneau::Reserve->value)->count(),
            'verrouilles' => $dansLaPlage()->where('statut', StatutCreneau::Verrouille->value)->where('verrou_expire_a', '>', now())->count(),
        ];
    }
}
