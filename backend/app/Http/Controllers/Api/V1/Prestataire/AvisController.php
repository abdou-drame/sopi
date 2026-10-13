<?php

namespace App\Http\Controllers\Api\V1\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Resources\AvisPublicResource;
use App\Services\ConsultationAvisService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Prestataire — mes avis
 *
 * Routes réservées aux prestataires (jeton Sanctum + rôle `prestataire`). Un prestataire ne voit que les avis qui le concernent.
 */
class AvisController extends Controller
{
    public function __construct(private readonly ConsultationAvisService $avis) {}

    /**
     * Mes avis
     *
     * Mêmes données que la consultation par les clients : paginée (20 par page), du plus récent au plus ancien, auteur réduit au
     * prénom et à l'initiale du nom, `noteMoyenne` (une décimale, `null` sans avis) et `nombreAvis`.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000080","note":4,"commentaire":"Travail soigné et ponctuel.","auteur":"Awa D.","dateCreation":"2030-01-17T10:00:00+00:00"}],"links":{"first":"https://api.exemple.test/api/v1/prestataire/avis?page=1","last":"https://api.exemple.test/api/v1/prestataire/avis?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1},"noteMoyenne":4.0,"nombreAvis":1}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function index(Request $request)
    {
        $prestataire = $request->user()->prestataire ?? throw new AccessDeniedHttpException;

        return AvisPublicResource::collection($this->avis->lister($prestataire))->additional($this->avis->synthese($prestataire));
    }
}
