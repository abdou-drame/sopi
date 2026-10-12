<?php

namespace App\Services\Paiement;

/** Résultat d'un remboursement ou d'un versement : succès ou échec, et la référence de l'opération (null en cas d'échec). */
final readonly class ResultatOperation
{
    public function __construct(public bool $succes, public ?string $reference = null) {}
}
