<?php

namespace App\Services;

use App\Enums\StatutReservation;
use App\Events\Reservation\RappelRendezVous;
use App\Models\Reservation;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alertes de rendez-vous (RF20), appelées chaque minute par le scheduler. Une réservation CONFIRMEE dont le créneau commence
 * dans 2 h au plus (et pas encore commencé) reçoit UN SEUL rappel : la réservation est « réclamée » par un UPDATE conditionnel
 * sur rappel_envoye_le, ce qui empêche tout doublon même si deux exécutions se chevauchent.
 */
class RappelRendezVousService
{
    public const FENETRE_HEURES = 2;

    public const TAILLE_LOT = 100;

    /** Envoie les rappels dus. Renvoie le nombre de réservations rappelées. */
    public function rappeler(): int
    {
        $maintenant = now();
        $limite = $maintenant->copy()->addHours(self::FENETRE_HEURES);
        $rappelees = 0;

        Reservation::query()
            ->where('statut', StatutReservation::Confirmee->value)
            ->whereNull('rappel_envoye_le')
            ->whereHas('creneau', fn ($q) => $q->where('date_debut', '>', $maintenant)->where('date_debut', '<=', $limite))
            ->chunkById(self::TAILLE_LOT, function ($lot) use ($maintenant, &$rappelees) {
                foreach ($lot as $reservation) {
                    try {
                        $reclamee = Reservation::query()
                            ->whereKey($reservation->getKey())
                            ->where('statut', StatutReservation::Confirmee->value)
                            ->whereNull('rappel_envoye_le')
                            ->update(['rappel_envoye_le' => $maintenant]);

                        if ($reclamee === 1) {
                            RappelRendezVous::dispatch($reservation->refresh());
                            $rappelees++;
                        }
                    } catch (Throwable $e) {
                        Log::warning('Rappel de rendez-vous échoué.', ['reservation' => $reservation->getKey(), 'erreur' => $e::class]);
                    }
                }
            });

        return $rappelees;
    }
}
