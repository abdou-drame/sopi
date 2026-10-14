<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListeReservationsRequest;
use App\Http\Resources\ReservationAdminResource;
use App\Services\ReservationsAdminService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Administration — réservations
 *
 * Routes de lecture réservées aux administrateurs (jeton Sanctum + rôle `administrateur`). Le code de réservation n'est jamais renvoyé.
 */
class ReservationAdminController extends Controller
{
    public function __construct(private readonly ReservationsAdminService $service) {}

    /**
     * Liste des réservations
     *
     * Toutes les réservations, de la plus récente à la plus ancienne (20 par page). Filtres combinables : `statut`, `prestataire`
     * (identifiant du profil prestataire), `client` (identifiant du compte), `debut` / `fin` (jour de début du créneau) et `q`
     * (code de réservation, recherche partielle). Le client n'apparaît que par son prénom et l'initiale de son nom ;
     * `statutPaiement` est celui du paiement qui représente la réservation (`null` sans paiement).
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000020","statut":"confirmee","client":{"id":"0198a1b2-0000-7000-8000-000000000001","prenom":"Awa","initialeNom":"D."},"prestataire":{"id":"0198a1b2-0000-7000-8000-000000000012","nomEntreprise":"Garage Exemple"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000030","nom":"Vidange"},"creneau":{"id":"0198a1b2-0000-7000-8000-000000000050","debut":"2030-01-21T09:00:00+00:00","fin":"2030-01-21T10:00:00+00:00"},"prixApplique":10000,"statutPaiement":"reussi","dateCreation":"2030-01-20T08:00:00+00:00"}],"links":{"first":"https://api.exemple.test/api/v1/admin/reservations?page=1","last":"https://api.exemple.test/api/v1/admin/reservations?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le prestataire doit être un identifiant valide.","errors":{"prestataire":["Le prestataire doit être un identifiant valide."]}}
     */
    public function index(ListeReservationsRequest $requete)
    {
        return ReservationAdminResource::collection($this->service->lister($requete->filtres()));
    }

    /**
     * Détail d'une réservation
     *
     * Client (nom complet), prestataire, prestation, créneau, prix appliqué, `paiement` (statut, acompte, commission, remboursement dû
     * ou non, nombre de tentatives de remboursement, dates), `avis` et `litige` éventuels (`null` sinon) et `historique` des dates
     * clés. Le code de réservation et la référence de paiement ne sont pas exposés ici.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000020
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000020","statut":"terminee","prestataire":{"id":"0198a1b2-0000-7000-8000-000000000012","nomEntreprise":"Garage Exemple"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000030","nom":"Vidange","dureeMinutes":60},"creneau":{"id":"0198a1b2-0000-7000-8000-000000000050","debut":"2030-01-21T09:00:00+00:00","fin":"2030-01-21T10:00:00+00:00"},"prixApplique":10000,"client":{"id":"0198a1b2-0000-7000-8000-000000000001","prenom":"Awa","nom":"Diallo"},"motifRefus":null,"paiement":{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"reussi","montantAcompte":3000,"commission":1000,"remboursementDu":false,"remboursementDuLe":null,"tentativesRemboursement":0,"tentativesEpuisees":false,"payeLe":"2030-01-20T09:00:00+00:00","rembourseLe":null,"crediteLe":"2030-01-21T10:05:00+00:00"},"nombrePaiements":1,"avis":{"id":"0198a1b2-0000-7000-8000-000000000070","note":5,"statut":"publie","dateCreation":"2030-01-21T12:00:00+00:00"},"litige":null,"historique":{"creeeLe":"2030-01-20T08:00:00+00:00","accepteeLe":"2030-01-20T08:30:00+00:00","payeLe":"2030-01-20T09:00:00+00:00","remboursementDuLe":null,"rembourseLe":null,"debutCreneau":"2030-01-21T09:00:00+00:00","finCreneau":"2030-01-21T10:00:00+00:00","misAJourLe":"2030-01-21T10:05:00+00:00"}}
     * @response 404 {"message":"Réservation introuvable."}
     */
    public function show(string $id): JsonResponse|ReservationAdminResource
    {
        $reservation = $this->service->trouver($id);

        return $reservation === null
            ? response()->json(['message' => 'Réservation introuvable.'], Response::HTTP_NOT_FOUND)
            : ReservationAdminResource::detail($reservation);
    }
}
