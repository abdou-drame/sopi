<?php

namespace App\Http\Controllers\Api\V1\Prestataire;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prestataire\RepondreLitigeRequest;
use App\Http\Resources\LitigeResource;
use App\Models\Prestataire;
use App\Services\LitigeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Prestataire — litiges
 *
 * Routes réservées aux prestataires (jeton Sanctum + rôle `prestataire`). Un prestataire ne voit que les litiges qui le visent ;
 * le client n'y est identifié que par son prénom et l'initiale de son nom.
 */
class LitigeController extends Controller
{
    public function __construct(private readonly LitigeService $service) {}

    /**
     * Litiges me visant
     *
     * Paginée (20 par page), du plus récent au plus ancien.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000090","statut":"ouvert","motif":"Prestataire absent","description":"Le prestataire ne s'est pas présenté.","dateCreation":"2030-01-16T12:00:00+00:00","reservation":{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"confirmee","dateDebut":"2030-01-16T09:00:00+00:00","prestation":"Vidange"},"nombrePreuves":1,"reponsePrestataire":null,"reponseLe":null,"decision":null,"motifDecision":null,"clotureLe":null,"client":{"prenom":"Awa","initialeNom":"D"}}],"links":{"first":"https://api.exemple.test/api/v1/prestataire/litiges?page=1","last":"https://api.exemple.test/api/v1/prestataire/litiges?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function index(Request $requete)
    {
        return LitigeResource::liste($this->service->listerPourPrestataire($this->prestataire($requete)), LitigeResource::VUE_PRESTATAIRE);
    }

    /**
     * Détail d'un litige me visant
     *
     * Le litige visant un autre prestataire répond 404.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du litige. Example: 0198a1b2-0000-7000-8000-000000000090
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000090","statut":"ouvert","motif":"Prestataire absent","description":"Le prestataire ne s'est pas présenté.","dateCreation":"2030-01-16T12:00:00+00:00","reservation":{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"confirmee","dateDebut":"2030-01-16T09:00:00+00:00","prestation":"Vidange"},"nombrePreuves":0,"reponsePrestataire":null,"reponseLe":null,"decision":null,"motifDecision":null,"clotureLe":null,"preuves":[],"client":{"prenom":"Awa","initialeNom":"D"}}
     * @response 404 {"message":"Litige introuvable."}
     */
    public function show(Request $requete, string $id): JsonResponse|LitigeResource
    {
        $litige = $this->service->trouverPourPrestataire($this->prestataire($requete), $id);

        return $litige === null
            ? response()->json(['message' => 'Litige introuvable.'], Response::HTTP_NOT_FOUND)
            : LitigeResource::pour($litige, LitigeResource::VUE_PRESTATAIRE);
    }

    /**
     * Répondre à un litige
     *
     * Donne votre version des faits (10 à 1000 caractères). Une seule réponse par litige, tant qu'il est ouvert.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du litige. Example: 0198a1b2-0000-7000-8000-000000000090
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000090","statut":"ouvert","reponsePrestataire":"J'étais sur place à l'heure convenue.","reponseLe":"2030-01-16T13:00:00+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Litige introuvable."}
     * @response 409 {"message":"Vous avez déjà répondu à ce litige."}
     * @response 422 {"message":"La réponse doit contenir au moins 10 caractères.","errors":{"reponse":["La réponse doit contenir au moins 10 caractères."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function repondre(RepondreLitigeRequest $requete, string $id): JsonResponse|LitigeResource
    {
        try {
            $litige = $this->service->repondre($this->prestataire($requete), $id, $requete->reponse());
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }

        return LitigeResource::pour($litige, LitigeResource::VUE_PRESTATAIRE);
    }

    /**
     * Afficher une preuve d'un litige me visant
     *
     * Renvoie l'image en flux (Content-Type réel, `nosniff`, `Cache-Control: private`).
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du litige. Example: 0198a1b2-0000-7000-8000-000000000090
     * @urlParam preuve string required Identifiant de la preuve. Example: 0198a1b2-0000-7000-8000-000000000091
     *
     * @response 200 scenario="image" [Contenu binaire de l'image]
     * @response 404 {"message":"Preuve introuvable."}
     */
    public function preuve(Request $requete, string $id, string $preuve): JsonResponse|StreamedResponse
    {
        $litige = $this->service->trouverPourPrestataire($this->prestataire($requete), $id);

        return ($litige === null ? null : $this->service->fluxPreuve($litige, $preuve))
            ?? response()->json(['message' => 'Preuve introuvable.'], Response::HTTP_NOT_FOUND);
    }

    private function prestataire(Request $requete): Prestataire
    {
        return $requete->user()->prestataire ?? throw new AccessDeniedHttpException;
    }
}
