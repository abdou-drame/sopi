<?php

namespace App\Services\Paiement;

/** Session de paiement ouverte chez le prestataire : sa référence et l'adresse de la page où le client paie. */
final readonly class SessionPaiement
{
    public function __construct(public string $reference, public string $urlPaiement) {}
}
