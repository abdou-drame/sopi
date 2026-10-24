<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListeLitigesRequest;
use App\Http\Requests\Admin\TrancherLitigeRequest;
use App\Http\Resources\LitigeResource;
use App\Services\LitigeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Administration — litiges
 *
 * Routes réservées aux administrateurs (jeton Sanctum + rôle `administrateur`). La décision est motivée et définitive ;
 * elle ne déclenche aucun mouvement d'argent automatique.
 */
class LitigeController extends Controller
{
    public function __construct(private readonly LitigeService $service) {}

    /**
     * Liste des litiges
     *
     * Paginée (20 par page). Par défaut les litiges `ouvert`, du plus ancien au plus récent.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000090","statut":"ouvert","motif":"Prestataire absent","description":"Le prestataire ne s'est pas présenté.","dateCreation":"2030-01-16T12:00:00+00:00","reservation":{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"confirmee","dateDebut":"2030-01-16T09:00:00+00:00","prestation":"Vidange"},"nombrePreuves":1,"reponsePrestataire":null,"reponseLe":null,"decision":null,"motifDecision":null,"clotureLe":null,"client":{"id":"0198a1b2-0000-7000-8000-000000000001","nom":"Diallo","prenom":"Awa","telephone":"+221770000001","email":null},"prestataire":{"id":"0198a1b2-0000-7000-8000-000000000002","nom":"Diop","prenom":"Moussa","telephone":"+221770000002","email":null,"nomEntreprise":"Garage Exemple"},"reservationCode":"A1B2C3","prestationTermineeLe":null,"prixApplique":15000,"paiements":[],"decidePar":null,"sanctionId":null}],"links":{"first":"https://api.exemple.test/api/v1/admin/litiges?page=1","last":"https://api.exemple.test/api/v1/admin/litiges?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le statut doit être ouvert, cloture ou tous.","errors":{"statut":["Le statut doit être ouvert, cloture ou tous."]}}
     */
    public function index(ListeLitigesRequest $requete)
    {
        return LitigeResource::liste($this->service->listerPourAdmin($requete->statut()), LitigeResource::VUE_ADMIN);
    }

    /**
     * Détail d'un litige
     *
     * Réservation, parties (coordonnées complètes), paiements de la réservation, preuves, réponse du prestataire et décision.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du litige. Example: 0198a1b2-0000-7000-8000-000000000090
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000090","statut":"ouvert","motif":"Prestataire absent","description":"Le prestataire ne s'est pas présenté.","dateCreation":"2030-01-16T12:00:00+00:00","reservation":{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"confirmee","dateDebut":"2030-01-16T09:00:00+00:00","prestation":"Vidange"},"nombrePreuves":0,"reponsePrestataire":null,"reponseLe":null,"decision":null,"motifDecision":null,"clotureLe":null,"preuves":[],"client":{"id":"0198a1b2-0000-7000-8000-000000000001","nom":"Diallo","prenom":"Awa","telephone":"+221770000001","email":null},"prestataire":{"id":"0198a1b2-0000-7000-8000-000000000002","nom":"Diop","prenom":"Moussa","telephone":"+221770000002","email":null,"nomEntreprise":"Garage Exemple"},"reservationCode":"A1B2C3","prestationTermineeLe":null,"prixApplique":15000,"paiements":[{"id":"0198a1b2-0000-7000-8000-000000000070","statut":"paye","montantAcompte":4500,"commission":1500,"payeLe":"2030-01-15T09:00:00+00:00","rembourseLe":null}],"decidePar":null,"sanctionId":null}
     * @response 404 {"message":"Litige introuvable."}
     */
    public function show(string $id): JsonResponse|LitigeResource
    {
        $litige = $this->service->trouverPourAdmin($id);

        return $litige === null
            ? response()->json(['message' => 'Litige introuvable.'], Response::HTTP_NOT_FOUND)
            : LitigeResource::pour($litige, LitigeResource::VUE_ADMIN);
    }

    /**
     * Trancher un litige
     *
     * Clôture un litige ouvert par une décision motivée (`rejet`, `avertissement`, `suspension_temporaire` ou `bannissement_definitif`). Une décision de sanction pose la sanction sur le `prestataire` (défaut) ou le `client` dans la même transaction que la clôture (voir « Administration — sanctions » pour ses effets). Un litige déjà clôturé répond 409, y compris
     * si deux décisions arrivent en même temps : une seule est retenue.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du litige. Example: 0198a1b2-0000-7000-8000-000000000090
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000090","statut":"cloture","decision":"rejet","motifDecision":"Signalement non fondé.","clotureLe":"2030-01-17T09:00:00+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Litige introuvable."}
     * @response 409 {"message":"Ce litige est déjà clôturé."}
     * @response 422 {"message":"Le motif de la décision est obligatoire.","errors":{"motif":["Le motif de la décision est obligatoire."]}}
     */
    public function trancher(TrancherLitigeRequest $requete, string $id): JsonResponse|LitigeResource
    {
        try {
            $litige = $this->service->trancher($requete->user(), $id, $requete->decision(), $requete->motif(), $requete->cible(), $requete->dateFin());
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }

        return LitigeResource::pour($litige, LitigeResource::VUE_ADMIN);
    }

    /**
     * Afficher une preuve d'un litige
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
        $litige = $this->service->trouverPourAdmin($id);

        return ($litige === null ? null : $this->service->fluxPreuve($litige, $preuve))
            ?? response()->json(['message' => 'Preuve introuvable.'], Response::HTTP_NOT_FOUND);
    }
}
