<?php

namespace App\Services\Paiement;

use App\Enums\StatutPaiement;

/** Événement de paiement reçu par webhook. Le statut vaut REUSSI ou ECHOUE ; le montant est une chaîne décimale (« 3000.00 »). */
final readonly class EvenementPaiement
{
    public function __construct(public string $reference, public StatutPaiement $statut, public string $montant) {}
}
