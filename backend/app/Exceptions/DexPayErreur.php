<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Échec d'un appel à DexPay (réponse inattendue, refus HTTP, service injoignable ou trop lent). Le message est technique et
 * ne contient jamais de clé, de corps de requête ni de numéro : seulement le type d'échec et, le cas échéant, le code HTTP.
 * $extrait : début (déjà masqué) de la réponse de DexPay, réservé au journal.
 */
class DexPayErreur extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $statutHttp = null, public readonly string $extrait = '')
    {
        parent::__construct($message);
    }
}
