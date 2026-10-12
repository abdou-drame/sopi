<?php

namespace App\Events\Reservation;

use App\Enums\Role;
use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une réservation vient d'être annulée (ANNULEE, créneau libéré). `$par` indique qui a annulé (le client en M5.4).
 * Publié après validation de la transaction. Aucun écouteur pour l'instant : les notifications viendront en M10.
 */
class ReservationAnnulee implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation, public readonly Role $par) {}
}
