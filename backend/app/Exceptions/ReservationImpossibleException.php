<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Refus métier d'une réservation : le message (en français) et le code HTTP sont renvoyés tels quels au client.
 * $diagnostic : TEMPORAIRE, détail d'un échec DexPay (déjà masqué) renvoyé seulement si DEXPAY_DIAGNOSTIC_REPONSE est actif.
 */
class ReservationImpossibleException extends RuntimeException
{
    /** @param array<string, mixed>|null $diagnostic */
    public function __construct(string $message, public readonly int $statut, public readonly ?array $diagnostic = null)
    {
        parent::__construct($message);
    }
}
