<?php

namespace App\Exceptions;

use RuntimeException;

/** Refus métier d'une réservation : le message (en français) et le code HTTP sont renvoyés tels quels au client. */
class ReservationImpossibleException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statut)
    {
        parent::__construct($message);
    }
}
