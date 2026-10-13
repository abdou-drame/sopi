<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PoserSanctionRequest;
use App\Http\Resources\SanctionResource;
use App\Services\SanctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Administration — sanctions
 *
 * Routes réservées aux administrateurs (jeton Sanctum + rôle `administrateur`). Poser et lever une sanction sont limités à 10 requêtes par minute.
 * Une suspension ou un bannissement est « tout ou rien » : sanction, statut du compte et révocation de tous ses jetons dans une seule
 * transaction. Le compte ne peut plus se connecter (le message de refus indique le motif et, pour une suspension, la date de fin),
 * disparaît de la recherche et du profil public, et ses réservations à venir (en attente, acceptées, confirmées pour un prestataire ;
 * en attente et acceptées pour un client) sont annulées, un prestataire sanctionné voyant l'acompte versé remboursé en intégralité.
 * Une prestation en cours va à son terme. Le solde et les retraits ne sont pas modifiés. Un administrateur ne peut pas être sanctionné.
 */
class SanctionController extends Controller
{
    public function __construct(private readonly SanctionService $service) {}

    /**
     * Poser une sanction
     *
     * Sanctionne un client ou un prestataire. Un compte ne peut porter qu'une seule suspension ou un seul bannissement actif à la fois.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de l'utilisateur sanctionné. Example: 0198a1b2-0000-7000-8000-000000000002
     *
     * @response 201 {"id":"0198a1b2-0000-7000-8000-000000000100","utilisateurId":"0198a1b2-0000-7000-8000-000000000002","type":"suspension_temporaire","motif":"Comportement injurieux envers un client.","dateFin":"2030-02-01T00:00:00+00:00","litigeId":null,"avisId":null,"posePar":"0198a1b2-0000-7000-8000-000000000001","dateCreation":"2030-01-20T10:00:00+00:00","active":true,"leveeLe":null,"leveePar":null}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Un administrateur ne peut pas être sanctionné."}
     * @response 404 {"message":"Utilisateur introuvable."}
     * @response 409 {"message":"Ce compte fait déjà l'objet d'une sanction active."}
     * @response 422 {"message":"La date de fin de la suspension est obligatoire.","errors":{"dateFin":["La date de fin de la suspension est obligatoire."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function store(PoserSanctionRequest $requete, string $id): JsonResponse
    {
        $cible = $this->service->trouverUtilisateur($id);
        if ($cible === null) {
            return response()->json(['message' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $litige = $requete->litige();
        if ($requete->validated('litigeId') !== null && $litige === null) {
            return response()->json(['message' => 'Litige introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $avis = $requete->avis();
        if ($requete->validated('avisId') !== null && $avis === null) {
            return response()->json(['message' => 'Avis introuvable.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $sanction = $this->service->poser($requete->user(), $cible, $requete->type(), $requete->motif(), $requete->dateFin(), $litige, $avis);
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }

        return (new SanctionResource($sanction))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Historique des sanctions d'un utilisateur
     *
     * Toutes les sanctions du compte, levées ou non, de la plus récente à la plus ancienne. Jamais supprimées.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de l'utilisateur. Example: 0198a1b2-0000-7000-8000-000000000002
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000100","utilisateurId":"0198a1b2-0000-7000-8000-000000000002","type":"avertissement","motif":"Retards répétés.","dateFin":null,"litigeId":null,"avisId":null,"posePar":"0198a1b2-0000-7000-8000-000000000001","dateCreation":"2030-01-20T10:00:00+00:00","active":true,"leveeLe":null,"leveePar":null}]}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Utilisateur introuvable."}
     */
    public function index(Request $requete, string $id)
    {
        $utilisateur = $this->service->trouverUtilisateur($id);

        return $utilisateur === null
            ? response()->json(['message' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND)
            : SanctionResource::collection($this->service->historique($utilisateur));
    }

    /**
     * Lever une sanction
     *
     * Met fin à une sanction active. Pour une suspension ou un bannissement, le compte redevient actif. Une sanction déjà levée répond 409.
     * Les suspensions arrivées à échéance sont levées automatiquement (toutes les 5 minutes).
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la sanction. Example: 0198a1b2-0000-7000-8000-000000000100
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000100","utilisateurId":"0198a1b2-0000-7000-8000-000000000002","type":"suspension_temporaire","motif":"Comportement injurieux envers un client.","dateFin":"2030-02-01T00:00:00+00:00","litigeId":null,"avisId":null,"posePar":"0198a1b2-0000-7000-8000-000000000001","dateCreation":"2030-01-20T10:00:00+00:00","active":false,"leveeLe":"2030-01-21T08:00:00+00:00","leveePar":"0198a1b2-0000-7000-8000-000000000001"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Sanction introuvable."}
     * @response 409 {"message":"Cette sanction est déjà levée."}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function lever(Request $requete, string $id): JsonResponse|SanctionResource
    {
        try {
            return new SanctionResource($this->service->lever($requete->user(), $id));
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }
    }
}
