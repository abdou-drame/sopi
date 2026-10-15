<?php

namespace App\Services\Paiement;

/**
 * Résultat d'un remboursement ou d'un versement : succès ou échec, et la référence de l'opération.
 * enCours : le versement est accepté mais pas encore terminé (le résultat final arrivera par webhook).
 * motif : cause technique d'un échec (ex. « http_400 »), sans donnée sensible, pour le journal de l'équipe.
 */
final readonly class ResultatOperation
{
    public function __construct(
        public bool $succes,
        public ?string $reference = null,
        public bool $enCours = false,
        public ?string $motif = null,
    ) {}
}
