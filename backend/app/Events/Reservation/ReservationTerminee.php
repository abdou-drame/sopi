<?php

namespace App\Events\Reservation;

use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Le prestataire a terminé la prestation (TERMINEE). Publié après validation de la transaction.
 * Aucun écouteur pour l'instant : le crédit du solde viendra en M7, les notifications en M10.
 */
class ReservationTerminee implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation) {}
}
