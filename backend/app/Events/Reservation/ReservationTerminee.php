<?php

namespace App\Events\Reservation;

use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Le prestataire a terminé la prestation (TERMINEE). Publié après validation de la transaction.
 * Le crédit du solde est fait avant, dans la transaction de « terminer » (M7.2). Aucun écouteur pour l'instant : les
 * notifications viendront en M10.
 */
class ReservationTerminee implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation) {}
}
