<?php

namespace App\Http\Controllers\Api\V1\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prestataire\CreerPrestationRequest;
use App\Http\Requests\Prestataire\ModifierPrestationRequest;
use App\Http\Resources\PrestationResource;
use App\Models\Prestataire;
use App\Services\PrestationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Prestataire — catalogue de prestations
 *
 * Routes réservées aux prestataires (jeton Sanctum + rôle `prestataire`). Le domaine d'une prestation est
 * toujours le métier du prestataire : il n'est jamais saisi. Une prestation ne se supprime pas, elle se désactive.
 */
class PrestationController extends Controller
{
    public function __construct(private readonly PrestationService $service) {}

    /**
     * Mes prestations
     *
     * Toutes les prestations du prestataire connecté, actives et inactives, les plus récentes d'abord (sans pagination).
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000020","nom":"Vidange complète","domaine":"mecanique","dureeMinutes":60,"prix":15000,"actif":true}]}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function index(Request $request)
    {
        return PrestationResource::collection($this->service->lister($this->prestataire($request)));
    }

    /**
     * Ajouter une prestation
     *
     * Le domaine est déduit du métier du prestataire (tout champ `domaine` envoyé est ignoré).
     * Le nom doit être unique chez le prestataire, sans tenir compte de la casse ni des espaces superflus.
     *
     * @authenticated
     *
     * @response 201 {"id":"0198a1b2-0000-7000-8000-000000000020","nom":"Vidange complète","domaine":"mecanique","dureeMinutes":60,"prix":15000,"actif":true}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Vous proposez déjà une prestation avec ce nom.","errors":{"nom":["Vous proposez déjà une prestation avec ce nom."]}}
     */
    public function store(CreerPrestationRequest $request): JsonResponse
    {
        $prestation = $this->service->creer($this->prestataire($request), $request->validated());

        return (new PrestationResource($prestation))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Modifier une prestation
     *
     * Modification partielle : nom, durée, prix et/ou `actif` (`false` désactive, `true` réactive). Au moins un champ est requis.
     * La prestation d'un autre prestataire répond 404, comme une prestation inexistante.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la prestation. Example: 0198a1b2-0000-7000-8000-000000000020
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000020","nom":"Vidange complète","domaine":"mecanique","dureeMinutes":75,"prix":17500,"actif":false}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Prestation introuvable."}
     * @response 422 {"message":"Le prix doit être supérieur à 0.","errors":{"prix":["Le prix doit être supérieur à 0."]}}
     */
    public function update(ModifierPrestationRequest $request, string $id): JsonResponse|PrestationResource
    {
        $prestation = $this->service->modifier($this->prestataire($request), $id, $request->donnees());

        if ($prestation === null) {
            return response()->json(['message' => 'Prestation introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return new PrestationResource($prestation);
    }

    private function prestataire(Request $request): Prestataire
    {
        return $request->user()->prestataire ?? throw new AccessDeniedHttpException;
    }
}
