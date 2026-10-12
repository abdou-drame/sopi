<?php

namespace App\Events\Reservation;

use App\Enums\CauseExpiration;
use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une réservation a été annulée automatiquement (ANNULEE, créneau libéré) : `$cause` dit pourquoi. Publié après
 * validation de la transaction. Aucun écouteur pour l'instant : les notifications viendront en M10.
 */
class ReservationExpiree implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation, public readonly CauseExpiration $cause) {}
}
