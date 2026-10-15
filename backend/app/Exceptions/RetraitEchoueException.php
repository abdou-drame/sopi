<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le prestataire de paiement a clairement refusé le versement : le retrait est ECHOUE et le solde a été recrédité.
 * Réponse 503 comme le paiement d'acompte (/payer) : le service externe est en cause, la demande peut être refaite.
 * $diagnostic : TEMPORAIRE, détail de l'échec (déjà masqué) renvoyé seulement si DEXPAY_DIAGNOSTIC_REPONSE est actif.
 */
class RetraitEchoueException extends RuntimeException
{
    /** @param array<string, mixed>|null $diagnostic */
    public function __construct(public readonly ?array $diagnostic = null)
    {
        parent::__construct();
    }

    public function render(): JsonResponse
    {
        return response()->json(
            ['message' => 'Le versement n\'a pas pu être effectué. Votre solde n\'a pas été débité. Veuillez réessayer.']
                + ($this->diagnostic !== null ? ['diagnostic' => $this->diagnostic] : []),
            Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
