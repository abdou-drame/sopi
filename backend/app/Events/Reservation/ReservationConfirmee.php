<?php

namespace App\Events\Reservation;

use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une réservation vient d'être confirmée par le webhook de paiement (ACCEPTEE vers CONFIRMEE, code généré).
 * Publié après validation de la transaction.
 */
class ReservationConfirmee implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation) {}
}
