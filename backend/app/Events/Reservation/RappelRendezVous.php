<?php

namespace App\Events\Reservation;

use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Le créneau d'une réservation CONFIRMEE commence bientôt (RF20) : rappel au client et au prestataire.
 * Publié une seule fois par réservation (colonne rappel_envoye_le), après validation de la transaction.
 */
class RappelRendezVous implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation) {}
}
