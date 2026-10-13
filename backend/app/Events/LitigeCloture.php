<?php

namespace App\Events;

use App\Models\Litige;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un administrateur vient de clôturer un litige par une décision. Publié après validation de la transaction.
 */
class LitigeCloture implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Litige $litige) {}
}
