<?php

namespace App\Events;

use App\Models\Prestataire;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** L'administrateur vient de refuser un dossier d'inscription de prestataire (compte REFUSE). Publié après validation de la transaction (M10). */
class DemandePrestataireRefusee implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Prestataire $prestataire) {}
}
