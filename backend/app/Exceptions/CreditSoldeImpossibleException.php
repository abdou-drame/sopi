<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le solde du prestataire n'a pas pu être crédité (panne de base). La fin de prestation est alors annulée en entier.
 * Le message renvoyé au client est volontairement générique ; la cause technique reste dans l'exception précédente (journal).
 */
class CreditSoldeImpossibleException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(
            ['message' => 'La fin de la prestation n\'a pas pu être enregistrée. Veuillez réessayer.'],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
