<?php

namespace App\Exceptions;

use RuntimeException;

/** Le corps d'un webhook de paiement est illisible ou incomplet. */
class EvenementPaiementIllisible extends RuntimeException {}
