<?php

namespace App\Events;

use App\Models\Avis;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un client vient de déposer un avis sur une réservation terminée. Publié après validation de la transaction.
 */
class AvisDepose implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Avis $avis) {}
}
