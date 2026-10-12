<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WebhookPaiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook du prestataire de paiement. Route publique, hors de la documentation : seul le prestataire de paiement l'appelle,
 * et sa signature HMAC est vérifiée sur le corps brut avant toute lecture.
 *
 * @hideFromAPIDocumentation
 */
class WebhookPaiementController extends Controller
{
    /** Taille maximale du corps accepté (64 Ko). */
    public const TAILLE_MAX = 65536;

    public function __construct(private readonly WebhookPaiementService $service) {}

    /** Reçoit un événement de paiement (réussi ou échoué) et répond 200, 401 (signature), 413 (trop gros) ou 422. */
    public function __invoke(Request $requete): JsonResponse
    {
        if ((int) $requete->header('Content-Length', '0') > self::TAILLE_MAX) {
            return response()->json(['message' => 'Corps trop volumineux.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        // Corps BRUT : la signature se vérifie sur les octets reçus, jamais sur un corps recodé.
        $corps = $requete->getContent();
        if (strlen($corps) > self::TAILLE_MAX) {
            return response()->json(['message' => 'Corps trop volumineux.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        [$statut, $message] = $this->service->traiter($corps, $requete->header((string) config('sopi.paiement.signature_header')));

        return response()->json(['message' => $message], $statut);
    }
}
