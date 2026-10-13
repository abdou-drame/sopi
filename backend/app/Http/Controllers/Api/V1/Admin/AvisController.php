<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListeAvisRequest;
use App\Http\Requests\Admin\MasquerAvisRequest;
use App\Http\Requests\Admin\ModererAvisRequest;
use App\Http\Resources\AvisAdminResource;
use App\Services\ModerationAvisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Administration — modération des avis
 *
 * Routes réservées aux administrateurs (jeton Sanctum + rôle `administrateur`). Masquer un avis (« supprimer ») le retire de la note
 * moyenne, des listes d'avis, de la recherche et du profil public ; la ligne est conservée et son auteur continue de la voir, avec
 * l'indicateur `affichePubliquement: false`. Chaque décision est tracée (administrateur, date, motif). Les modifications sont limitées
 * à 10 requêtes par minute.
 */
class AvisController extends Controller
{
    public function __construct(private readonly ModerationAvisService $service) {}

    /**
     * Liste des avis
     *
     * Paginée (20 par page), du plus récent au plus ancien, tous prestataires confondus. Filtres facultatifs : `prestataire`, `note`,
     * `masque`, `signale`, `aTraiter` (signalés et pas encore modérés).
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000080","note":1,"commentaire":"Service décevant.","dateCreation":"2030-01-17T10:00:00+00:00","reservationId":"0198a1b2-0000-7000-8000-000000000060","client":{"id":"0198a1b2-0000-7000-8000-000000000001","nom":"Diallo","prenom":"Awa"},"prestataire":{"id":"0198a1b2-0000-7000-8000-000000000003","nomEntreprise":"Garage Exemple"},"statut":"signale","signaleLe":"2030-01-18T08:00:00+00:00","motifSignalement":"Cet avis concerne une autre prestation que la mienne.","modereLe":null,"moderePar":null,"motifModeration":null,"masque":false,"masqueLe":null}],"links":{"first":"https://api.exemple.test/api/v1/admin/avis?page=1","last":"https://api.exemple.test/api/v1/admin/avis?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"La note doit être comprise entre 1 et 5.","errors":{"note":["La note doit être comprise entre 1 et 5."]}}
     */
    public function index(ListeAvisRequest $requete)
    {
        return AvisAdminResource::collection($this->service->lister($requete->filtres()));
    }

    /**
     * Masquer un avis
     *
     * Retire l'avis de l'affichage public. Motif obligatoire. Un avis déjà masqué répond 409, y compris si deux requêtes arrivent
     * en même temps.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de l'avis. Example: 0198a1b2-0000-7000-8000-000000000080
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000080","note":1,"commentaire":"Service décevant.","dateCreation":"2030-01-17T10:00:00+00:00","reservationId":"0198a1b2-0000-7000-8000-000000000060","client":{"id":"0198a1b2-0000-7000-8000-000000000001","nom":"Diallo","prenom":"Awa"},"prestataire":{"id":"0198a1b2-0000-7000-8000-000000000003","nomEntreprise":"Garage Exemple"},"statut":"supprime","signaleLe":null,"motifSignalement":null,"modereLe":"2030-01-19T09:00:00+00:00","moderePar":"0198a1b2-0000-7000-8000-000000000009","motifModeration":"Propos injurieux sans rapport avec la prestation.","masque":true,"masqueLe":"2030-01-19T09:00:00+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Avis introuvable."}
     * @response 409 {"message":"Cet avis est déjà masqué."}
     * @response 422 {"message":"Le motif de la modération est obligatoire.","errors":{"motif":["Le motif de la modération est obligatoire."]}}
     */
    public function masquer(MasquerAvisRequest $requete, string $id): JsonResponse|AvisAdminResource
    {
        return $this->repondre(fn () => $this->service->masquer($requete->user(), $id, $requete->motif()));
    }

    /**
     * Conserver un avis
     *
     * Décide, après examen d'un signalement, que l'avis reste affiché. Motif facultatif. Un avis déjà modéré répond 409.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de l'avis. Example: 0198a1b2-0000-7000-8000-000000000080
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000080","note":1,"commentaire":"Service décevant.","dateCreation":"2030-01-17T10:00:00+00:00","reservationId":"0198a1b2-0000-7000-8000-000000000060","client":{"id":"0198a1b2-0000-7000-8000-000000000001","nom":"Diallo","prenom":"Awa"},"prestataire":{"id":"0198a1b2-0000-7000-8000-000000000003","nomEntreprise":"Garage Exemple"},"statut":"publie","signaleLe":"2030-01-18T08:00:00+00:00","motifSignalement":"Avis injuste.","modereLe":"2030-01-19T09:00:00+00:00","moderePar":"0198a1b2-0000-7000-8000-000000000009","motifModeration":null,"masque":false,"masqueLe":null}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Avis introuvable."}
     * @response 409 {"message":"Cet avis a déjà été modéré."}
     */
    public function conserver(ModererAvisRequest $requete, string $id): JsonResponse|AvisAdminResource
    {
        return $this->repondre(fn () => $this->service->conserver($requete->user(), $id, $requete->motif()));
    }

    /**
     * Rétablir un avis masqué
     *
     * L'avis est de nouveau affiché et compte dans la note moyenne. La trace de la modération est conservée. Un avis non masqué répond 409.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de l'avis. Example: 0198a1b2-0000-7000-8000-000000000080
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000080","note":1,"commentaire":"Service décevant.","dateCreation":"2030-01-17T10:00:00+00:00","reservationId":"0198a1b2-0000-7000-8000-000000000060","client":{"id":"0198a1b2-0000-7000-8000-000000000001","nom":"Diallo","prenom":"Awa"},"prestataire":{"id":"0198a1b2-0000-7000-8000-000000000003","nomEntreprise":"Garage Exemple"},"statut":"publie","signaleLe":null,"motifSignalement":null,"modereLe":"2030-01-19T09:00:00+00:00","moderePar":"0198a1b2-0000-7000-8000-000000000009","motifModeration":"Propos injurieux sans rapport avec la prestation.","masque":false,"masqueLe":null}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Avis introuvable."}
     * @response 409 {"message":"Cet avis n'est pas masqué."}
     */
    public function retablir(Request $requete, string $id): JsonResponse|AvisAdminResource
    {
        return $this->repondre(fn () => $this->service->retablir($requete->user(), $id));
    }

    private function repondre(callable $action): JsonResponse|AvisAdminResource
    {
        try {
            return new AvisAdminResource($action());
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }
    }
}
