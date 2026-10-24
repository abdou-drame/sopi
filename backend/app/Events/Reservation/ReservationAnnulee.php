<?php

namespace App\Events\Reservation;

use App\Enums\CauseAnnulation;
use App\Enums\Role;
use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une réservation vient d'être annulée (ANNULEE, créneau libéré). `$par` indique qui a annulé (le client en M5.4, le prestataire en M5.7) et `$cause` pourquoi. `$montantCredite` : montant crédité au prestataire (annulation par le client d'une réservation payée), sinon null.
 * Publié après validation de la transaction.
 */
class ReservationAnnulee implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation, public readonly Role $par, public readonly CauseAnnulation $cause = CauseAnnulation::Demande, public readonly ?string $montantCredite = null) {}
}
