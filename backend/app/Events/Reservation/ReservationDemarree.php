<?php

namespace App\Events\Reservation;

use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Le prestataire a démarré la prestation avec le code du client (EN_COURS). Publié après validation de la transaction.
 * Aucun écouteur pour l'instant : les notifications viendront en M10.
 */
class ReservationDemarree implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation) {}
}
