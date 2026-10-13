<?php

namespace App\Events;

use App\Models\Litige;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un client vient d'ouvrir un litige. Publié après validation de la transaction.
 * Aucun écouteur pour l'instant : les notifications viendront en M10.
 */
class LitigeOuvert implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Litige $litige) {}
}
