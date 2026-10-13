<?php

namespace App\Events;

use App\Models\Prestataire;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** L'administrateur vient de valider un dossier d'inscription de prestataire (compte ACTIF). Publié après validation de la transaction (M10). */
class DemandePrestataireAcceptee implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Prestataire $prestataire) {}
}
