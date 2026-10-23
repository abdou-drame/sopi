<?php

namespace App\Services;

use App\Enums\StatutReservation;
use App\Models\Prestataire;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Chiffres du tableau de bord du prestataire (RF10). Le jour et le mois sont ceux du fuseau de l'application
 * (Africa/Dakar) et se comptent sur la date de début du créneau.
 */
class TableauPrestataireService
{
    /**
     * @return array{rendezVousAujourdhui: int, terminesCeMois: int, planningDuJour: Collection<int, Reservation>}
     */
    public function chiffres(Prestataire $prestataire): array
    {
        $planning = $this->demandes($prestataire)
            ->whereIn('reservations.statut', [StatutReservation::Confirmee->value, StatutReservation::EnCours->value])
            ->whereHas('creneau', fn (Builder $c) => $c->whereBetween('date_debut', [now()->startOfDay(), now()->endOfDay()]))
            ->with(['creneau', 'prestation', 'client', 'paiements'])
            ->get()
            ->sortBy(fn (Reservation $r) => $r->creneau->date_debut->getTimestamp().$r->id)
            ->values();

        $terminesCeMois = $this->demandes($prestataire)
            ->where('reservations.statut', StatutReservation::Terminee->value)
            ->whereHas('creneau', fn (Builder $c) => $c->whereBetween('date_debut', [now()->startOfMonth(), now()->endOfMonth()]))
            ->count();

        return [
            'rendezVousAujourdhui' => $planning->count(),
            'terminesCeMois' => $terminesCeMois,
            'planningDuJour' => $planning,
        ];
    }

    private function demandes(Prestataire $prestataire): Builder
    {
        return Reservation::query()->whereHas('creneau', fn (Builder $q) => $q->where('prestataire_id', $prestataire->id));
    }
}
