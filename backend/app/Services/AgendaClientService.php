<?php

namespace App\Services;

use App\Enums\StatutCompte;
use App\Models\Prestataire;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Agenda d'un prestataire vu par un client (RF03) : uniquement les créneaux réellement réservables. */
class AgendaClientService
{
    public const JOURS_PAR_DEFAUT = 14;

    public const JOURS_MAX = 31;

    /** Prestataire dont le compte est actif, ou null (inconnu, identifiant invalide ou compte non actif). */
    public function prestataireVisible(string $id): ?Prestataire
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return Prestataire::query()
            ->whereHas('user', fn (Builder $q) => $q->where('statut', StatutCompte::Actif->value))
            ->find($id);
    }

    /**
     * Créneaux disponibles (libres, ou verrouillés avec un verrou expiré) qui commencent dans le futur, entre le début du jour $du
     * et la fin du jour $au (inclus), triés par début.
     */
    public function creneauxDisponibles(Prestataire $prestataire, Carbon $du, Carbon $au): Collection
    {
        return $prestataire->creneaux()
            ->libres()
            ->aVenir()
            ->where('date_debut', '>=', $du)
            ->where('date_debut', '<', $au->copy()->addDay()->startOfDay())
            ->orderBy('date_debut')
            ->get(['id', 'date_debut', 'date_fin']);
    }
}
