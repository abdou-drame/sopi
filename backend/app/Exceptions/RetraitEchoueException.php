<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le prestataire de paiement a clairement refusé le versement : le retrait est ECHOUE et le solde a été recrédité.
 * Réponse 503 comme le paiement d'acompte (/payer) : le service externe est en cause, la demande peut être refaite.
 */
class RetraitEchoueException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(
            ['message' => 'Le versement n\'a pas pu être effectué. Votre solde n\'a pas été débité. Veuillez réessayer.'],
            Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
