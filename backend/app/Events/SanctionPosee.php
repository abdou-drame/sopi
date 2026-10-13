<?php

namespace App\Events;

use App\Models\Sanction;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une sanction vient d'être posée. Publié après validation de la transaction.
 */
class SanctionPosee implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Sanction $sanction) {}
}
