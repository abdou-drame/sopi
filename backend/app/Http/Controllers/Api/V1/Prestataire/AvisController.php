<?php

namespace App\Http\Controllers\Api\V1\Prestataire;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prestataire\SignalerAvisRequest;
use App\Http\Resources\AvisPrestataireResource;
use App\Services\ConsultationAvisService;
use App\Services\ModerationAvisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Prestataire — mes avis
 *
 * Routes réservées aux prestataires (jeton Sanctum + rôle `prestataire`). Un prestataire ne voit que les avis qui le concernent.
 */
class AvisController extends Controller
{
    public function __construct(private readonly ConsultationAvisService $avis, private readonly ModerationAvisService $moderation) {}

    /**
     * Mes avis
     *
     * Avis masqués par l'administrateur exclus. Mêmes données que la consultation par les clients, plus `peutSignaler` : paginée (20 par page), du plus récent au plus ancien, auteur réduit au
     * prénom et à l'initiale du nom, `noteMoyenne` (une décimale, `null` sans avis) et `nombreAvis`.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000080","note":4,"commentaire":"Travail soigné et ponctuel.","auteur":"Awa D.","dateCreation":"2030-01-17T10:00:00+00:00","peutSignaler":true}],"links":{"first":"https://api.exemple.test/api/v1/prestataire/avis?page=1","last":"https://api.exemple.test/api/v1/prestataire/avis?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1},"noteMoyenne":4.0,"nombreAvis":1}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function index(Request $request)
    {
        $prestataire = $request->user()->prestataire ?? throw new AccessDeniedHttpException;

        return AvisPrestataireResource::collection($this->avis->lister($prestataire))->additional($this->avis->synthese($prestataire));
    }

    /**
     * Signaler un avis
     *
     * Demande à l'administrateur d'examiner un avis reçu (RF20). Un seul signalement par avis ; l'avis reste affiché tant que
     * l'administrateur n'a pas décidé. Un avis d'un autre prestataire ou masqué répond 404.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de l'avis. Example: 0198a1b2-0000-7000-8000-000000000080
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000080","signale":true,"signaleLe":"2030-01-18T08:00:00+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Avis introuvable."}
     * @response 409 {"message":"Cet avis a déjà été signalé."}
     * @response 422 {"message":"Le motif du signalement est obligatoire.","errors":{"motif":["Le motif du signalement est obligatoire."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function signaler(SignalerAvisRequest $requete, string $id): JsonResponse
    {
        $prestataire = $requete->user()->prestataire ?? throw new AccessDeniedHttpException;

        try {
            $avis = $this->moderation->signaler($prestataire, $id, $requete->motif());
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }

        return response()->json(['id' => $avis->id, 'signale' => true, 'signaleLe' => $avis->signale_le->toIso8601String()]);
    }
}
