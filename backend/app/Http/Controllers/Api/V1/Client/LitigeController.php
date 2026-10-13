<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\OuvrirLitigeRequest;
use App\Http\Resources\LitigeResource;
use App\Services\LitigeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Client — litiges
 *
 * Routes réservées aux clients (jeton Sanctum + rôle `client`). Un client ne voit que ses propres litiges. L'ouverture est limitée à 5 requêtes par minute et par utilisateur.
 */
class LitigeController extends Controller
{
    public function __construct(private readonly LitigeService $service) {}

    /**
     * Ouvrir un litige
     *
     * Signale un problème sur une de vos réservations `confirmee`, `en_cours` ou `terminee`. Envoi JSON, ou multipart pour joindre
     * jusqu'à 3 photos de preuve (`preuves[]`). Une réservation ne peut engendrer qu'un seul litige, même une fois clôturé. Le prestataire peut répondre,
     * puis un administrateur tranche. Aucun remboursement n'est déclenché automatiquement.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 201 {"id":"0198a1b2-0000-7000-8000-000000000090","statut":"ouvert","description":"Le prestataire ne s'est pas présenté au rendez-vous.","dateCreation":"2030-01-16T12:00:00+00:00","reservation":{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"confirmee","dateDebut":"2030-01-16T09:00:00+00:00","prestation":"Vidange"},"nombrePreuves":0,"reponsePrestataire":null,"reponseLe":null,"decision":null,"motifDecision":null,"clotureLe":null,"prestataire":{"nomEntreprise":"Garage Exemple"}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Réservation introuvable."}
     * @response 409 {"message":"Un litige a déjà été ouvert pour cette réservation."}
     * @response 422 {"message":"La description doit contenir au moins 10 caractères.","errors":{"description":["La description doit contenir au moins 10 caractères."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function store(OuvrirLitigeRequest $requete, string $id): JsonResponse
    {
        try {
            $litige = $this->service->ouvrir($requete->user(), $id, $requete->description(), $requete->preuves());
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }

        return LitigeResource::pour($this->service->trouverPourClient($requete->user(), $litige->id), LitigeResource::VUE_CLIENT)
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Mes litiges
     *
     * Paginée (20 par page), du plus récent au plus ancien.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000090","statut":"ouvert","description":"Le prestataire ne s'est pas présenté.","dateCreation":"2030-01-16T12:00:00+00:00","reservation":{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"confirmee","dateDebut":"2030-01-16T09:00:00+00:00","prestation":"Vidange"},"nombrePreuves":1,"reponsePrestataire":null,"reponseLe":null,"decision":null,"motifDecision":null,"clotureLe":null,"prestataire":{"nomEntreprise":"Garage Exemple"}}],"links":{"first":"https://api.exemple.test/api/v1/litiges?page=1","last":"https://api.exemple.test/api/v1/litiges?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function index(Request $requete)
    {
        return LitigeResource::liste($this->service->listerPourClient($requete->user()), LitigeResource::VUE_CLIENT);
    }

    /**
     * Détail d'un de mes litiges
     *
     * Avec la réponse du prestataire et la décision de l'administrateur quand elles existent. Le litige d'un autre client répond 404.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du litige. Example: 0198a1b2-0000-7000-8000-000000000090
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000090","statut":"cloture","description":"Le prestataire ne s'est pas présenté.","dateCreation":"2030-01-16T12:00:00+00:00","reservation":{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"confirmee","dateDebut":"2030-01-16T09:00:00+00:00","prestation":"Vidange"},"nombrePreuves":1,"reponsePrestataire":"J'étais sur place.","reponseLe":"2030-01-16T13:00:00+00:00","decision":"rejet","motifDecision":"Signalement non fondé.","clotureLe":"2030-01-17T09:00:00+00:00","preuves":[{"id":"0198a1b2-0000-7000-8000-000000000091","url":"/api/v1/litiges/0198a1b2-0000-7000-8000-000000000090/preuves/0198a1b2-0000-7000-8000-000000000091"}],"prestataire":{"nomEntreprise":"Garage Exemple"}}
     * @response 404 {"message":"Litige introuvable."}
     */
    public function show(Request $requete, string $id): JsonResponse|LitigeResource
    {
        $litige = $this->service->trouverPourClient($requete->user(), $id);

        return $litige === null
            ? response()->json(['message' => 'Litige introuvable.'], Response::HTTP_NOT_FOUND)
            : LitigeResource::pour($litige, LitigeResource::VUE_CLIENT);
    }

    /**
     * Afficher une preuve de mon litige
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
        $litige = $this->service->trouverPourClient($requete->user(), $id);

        return ($litige === null ? null : $this->service->fluxPreuve($litige, $preuve))
            ?? response()->json(['message' => 'Preuve introuvable.'], Response::HTTP_NOT_FOUND);
    }
}
