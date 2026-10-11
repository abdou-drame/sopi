<?php

namespace App\Exceptions;

use RuntimeException;

/** La demande n'est plus au statut en_attente : une décision a déjà été prise. */
class DemandeDejaTraitee extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cette demande a déjà été traitée.');
    }
}
