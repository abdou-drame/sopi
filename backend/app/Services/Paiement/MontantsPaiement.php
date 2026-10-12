<?php

namespace App\Services\Paiement;

/** Montants d'un paiement calculés par le serveur, en FCFA entiers au format « 3000.00 » (compatible decimal(12,2)). */
final readonly class MontantsPaiement
{
    public function __construct(public string $montantAcompte, public string $commission) {}
}
