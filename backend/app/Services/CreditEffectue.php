<?php

namespace App\Services;

/** Ce qu'un crédit de solde a réellement fait : montants lus au moment de l'écriture, dans la même transaction. */
final readonly class CreditEffectue
{
    public function __construct(
        public string $acompte,
        public string $commission,
        public string $montantCredite,
        public string $soldeApres,
    ) {}
}
