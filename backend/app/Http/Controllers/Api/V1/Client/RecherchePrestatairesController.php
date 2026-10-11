<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\RecherchePrestatairesRequest;
use App\Http\Resources\PrestataireRechercheResource;
use App\Services\RechercheService;

/**
 * @group Client — recherche de prestataires
 *
 * Routes réservées aux clients (jeton Sanctum + rôle `client`).
 */
class RecherchePrestatairesController extends Controller
{
    public function __construct(private readonly RechercheService $service) {}

    /**
     * Rechercher des prestataires
     *
     * Paginée (20 par page). Ne renvoie que les prestataires dont le compte est actif et qui ont au moins une prestation active.
     * Tous les filtres sont optionnels et combinables. Aucune donnée personnelle n'est renvoyée, et pas de note (les avis n'existent pas encore).
     * La recherche ignore la casse mais pas les accents. `prixMin` et `nombrePrestations` portent sur toutes les prestations actives du prestataire.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000002","nomEntreprise":"Garage Moussa","metier":"mecanique","quartier":"Malika","adresse":"Rue 10, près du marché","prixMin":5000,"nombrePrestations":3}],"links":{"first":"https://api.exemple.test/api/v1/prestataires?page=1","last":"https://api.exemple.test/api/v1/prestataires?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le prix maximum doit être supérieur ou égal au prix minimum.","errors":{"prix_max":["Le prix maximum doit être supérieur ou égal au prix minimum."]}}
     */
    public function __invoke(RecherchePrestatairesRequest $request)
    {
        return PrestataireRechercheResource::collection(
            $this->service->rechercher($request->filtres())->withQueryString(),
        );
    }
}
