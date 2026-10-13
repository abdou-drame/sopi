<?php

namespace App\Http\Controllers\Api\V1\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Resources\RetraitResource;
use App\Models\Prestataire;
use App\Services\RetraitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Prestataire — solde et retraits
 *
 * Routes réservées aux prestataires (jeton Sanctum + rôle `prestataire`). Un prestataire ne voit que son propre solde et ses
 * propres retraits ; le retrait d'un autre prestataire répond 404, comme un retrait inexistant. Les montants sont en FCFA.
 */
class RetraitController extends Controller
{
    public function __construct(private readonly RetraitService $service) {}

    /**
     * Mon solde
     *
     * Solde disponible et limites d'un retrait (en FCFA).
     *
     * @authenticated
     *
     * @response 200 {"solde":12500.0,"montantMinimumRetrait":1000,"montantMaximumRetrait":500000}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function solde(Request $request): JsonResponse
    {
        return response()->json([
            'solde' => (float) $this->prestataire($request)->fresh()->solde,
            'montantMinimumRetrait' => (int) config('sopi.retraits.montant_minimum'),
            'montantMaximumRetrait' => (int) config('sopi.retraits.montant_maximum'),
        ]);
    }

    /**
     * Mes retraits
     *
     * Historique paginé (20 par page), du plus récent au plus ancien. Le numéro de versement est masqué (3 derniers chiffres).
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000070","montant":5000.0,"statut":"reussi","telephoneVersement":"+221******567","dateDemande":"2030-01-15T08:50:00+00:00","dateTraitement":"2030-01-15T08:50:02+00:00"}],"links":{"first":"https://api.exemple.test/api/v1/prestataire/retraits?page=1","last":"https://api.exemple.test/api/v1/prestataire/retraits?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function index(Request $request)
    {
        return RetraitResource::collection($this->service->lister($this->prestataire($request)));
    }

    /**
     * Détail d'un retrait
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du retrait. Example: 0198a1b2-0000-7000-8000-000000000070
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000070","montant":5000.0,"statut":"reussi","telephoneVersement":"+221******567","dateDemande":"2030-01-15T08:50:00+00:00","dateTraitement":"2030-01-15T08:50:02+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Retrait introuvable."}
     */
    public function show(Request $request, string $id): JsonResponse|RetraitResource
    {
        $retrait = $this->service->trouver($this->prestataire($request), $id);

        return $retrait === null
            ? response()->json(['message' => 'Retrait introuvable.'], Response::HTTP_NOT_FOUND)
            : new RetraitResource($retrait);
    }

    private function prestataire(Request $request): Prestataire
    {
        return $request->user()->prestataire ?? throw new AccessDeniedHttpException;
    }
}
