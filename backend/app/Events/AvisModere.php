<?php

namespace App\Events;

use App\Models\Avis;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** L'administrateur vient de modérer un avis : masqué (`$masque` vrai) ou conservé. Publié après validation de la transaction (M10). */
class AvisModere implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Avis $avis, public readonly bool $masque) {}
}
