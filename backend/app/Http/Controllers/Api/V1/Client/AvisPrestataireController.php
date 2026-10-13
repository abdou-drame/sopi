<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\AvisPublicResource;
use App\Services\ConsultationAvisService;
use App\Services\ProfilPublicService;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Client — avis d'un prestataire
 *
 * Routes réservées aux clients (jeton Sanctum + rôle `client`). Seuls les prestataires dont le compte est actif sont consultables.
 */
class AvisPrestataireController extends Controller
{
    public function __construct(private readonly ProfilPublicService $profils, private readonly ConsultationAvisService $avis) {}

    /**
     * Avis d'un prestataire
     *
     * Paginée (20 par page), du plus récent au plus ancien. L'auteur n'apparaît que par son prénom et l'initiale de son nom.
     * `noteMoyenne` (arrondie à une décimale, `null` sans avis) et `nombreAvis` portent sur tous les avis du prestataire, pas sur la page.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du prestataire. Example: 0198a1b2-0000-7000-8000-000000000002
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000080","note":4,"commentaire":"Travail soigné et ponctuel.","auteur":"Awa D.","dateCreation":"2030-01-17T10:00:00+00:00"}],"links":{"first":"https://api.exemple.test/api/v1/prestataires/0198a1b2-0000-7000-8000-000000000002/avis?page=1","last":"https://api.exemple.test/api/v1/prestataires/0198a1b2-0000-7000-8000-000000000002/avis?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1},"noteMoyenne":4.0,"nombreAvis":1}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Prestataire introuvable."}
     */
    public function index(string $id)
    {
        $prestataire = $this->profils->visible($id);

        if ($prestataire === null) {
            return response()->json(['message' => 'Prestataire introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return AvisPublicResource::collection($this->avis->lister($prestataire))->additional($this->avis->synthese($prestataire));
    }
}
