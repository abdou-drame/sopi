<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListeDemandesRequest;
use App\Http\Resources\DemandePrestataireDetailResource;
use App\Http\Resources\DemandePrestataireResource;
use App\Services\DemandePrestataireService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Administration — demandes d'inscription
 *
 * Routes réservées aux administrateurs (jeton Sanctum + rôle `administrateur`).
 */
class DemandePrestataireController extends Controller
{
    public function __construct(private readonly DemandePrestataireService $service) {}

    /**
     * Liste des demandes des prestataires
     *
     * Paginée (20 par page). Par défaut les demandes `en_attente`, de la plus ancienne à la plus récente.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000002","nom":"Diop","prenom":"Moussa","nomEntreprise":"Garage Moussa","metier":"mecanique","quartier":"Malika","statutCompte":"en_attente","dateSoumission":"2026-10-12T09:30:00+00:00","nombreJustificatifs":2}],"links":{"first":"https://api.exemple.test/api/v1/admin/prestataires/demandes?page=1","last":"https://api.exemple.test/api/v1/admin/prestataires/demandes?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le statut doit être en_attente, actif, refuse ou tous.","errors":{"statut":["Le statut doit être en_attente, actif, refuse ou tous."]}}
     */
    public function index(ListeDemandesRequest $request)
    {
        return DemandePrestataireResource::collection($this->service->lister($request->statut()));
    }

    /**
     * Détail d'une demande
     *
     * Coordonnées du prestataire et liste de ses justificatifs (sans chemin de stockage).
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du prestataire. Example: 0198a1b2-0000-7000-8000-000000000002
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000002","nom":"Diop","prenom":"Moussa","telephone":"+221771234567","email":"moussa@example.com","nomEntreprise":"Garage Moussa","metier":"mecanique","quartier":"Malika","adresse":"Rue 10, près du marché","statutCompte":"en_attente","motifRefus":null,"dateSoumission":"2026-10-12T09:30:00+00:00","justificatifs":[{"id":"0198a1b2-0000-7000-8000-000000000010","typeDocument":"cni","statut":"en_attente","nomOriginal":"cni.png","typeMime":"image/png","taille":184320}]}
     * @response 404 {"message":"Demande introuvable."}
     */
    public function show(string $id): JsonResponse|DemandePrestataireDetailResource
    {
        $prestataire = $this->service->detail($id);

        if ($prestataire === null) {
            return response()->json(['message' => 'Demande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return new DemandePrestataireDetailResource($prestataire);
    }
}
