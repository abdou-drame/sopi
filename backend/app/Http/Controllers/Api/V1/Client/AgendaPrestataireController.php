<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ConsulterAgendaPrestataireRequest;
use App\Http\Resources\CreneauDisponibleResource;
use App\Services\AgendaClientService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Client — agenda d'un prestataire
 *
 * Routes réservées aux clients (jeton Sanctum + rôle `client`). Seuls les prestataires dont le compte est actif sont visibles.
 */
class AgendaPrestataireController extends Controller
{
    public function __construct(private readonly AgendaClientService $service) {}

    /**
     * Créneaux disponibles d'un prestataire
     *
     * Uniquement les créneaux réservables : libres (ou dont le verrou a expiré) et qui commencent dans le futur.
     * Les créneaux réservés, indisponibles ou verrouillés par un autre client n'apparaissent pas. Triés par début, sans pagination.
     * Par défaut : de maintenant à 14 jours plus tard. Intervalle maximal : 31 jours. Réponse jamais mise en cache (`Cache-Control: no-store`).
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du prestataire. Example: 0198a1b2-0000-7000-8000-000000000002
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-15T09:00:00+00:00","dateFin":"2030-01-15T10:00:00+00:00"}]}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Prestataire introuvable."}
     * @response 422 {"message":"L'intervalle ne peut pas dépasser 31 jours.","errors":{"au":["L'intervalle ne peut pas dépasser 31 jours."]}}
     */
    public function __invoke(ConsulterAgendaPrestataireRequest $request, string $id): JsonResponse
    {
        $prestataire = $this->service->prestataireVisible($id);

        if ($prestataire === null) {
            return response()->json(['message' => 'Prestataire introuvable.'], Response::HTTP_NOT_FOUND)
                ->header('Cache-Control', 'no-store');
        }

        [$du, $au] = $request->intervalle();
        $creneaux = $this->service->creneauxDisponibles($prestataire, $du, $au);

        return CreneauDisponibleResource::collection($creneaux)->response()->header('Cache-Control', 'no-store');
    }
}
