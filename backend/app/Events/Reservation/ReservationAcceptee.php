<?php

namespace App\Events\Reservation;

use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Le prestataire a accepté une demande (ACCEPTEE, en attente de paiement). Publié après validation de la transaction.
 * Aucun écouteur pour l'instant : les notifications viendront en M10.
 */
class ReservationAcceptee implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation) {}
}
