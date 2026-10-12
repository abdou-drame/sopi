<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CreerReservationRequest;
use App\Http\Requests\Client\ListerMesReservationsRequest;
use App\Http\Resources\ReservationClientResource;
use App\Http\Resources\ReservationResource;
use App\Services\ReservationService;
use App\Services\SuiviReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Client — réservations
 *
 * Routes réservées aux clients (jeton Sanctum + rôle `client`). La création est limitée à 20 requêtes par minute et par utilisateur, l'annulation à 30.
 */
class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $service, private readonly SuiviReservationService $suivi) {}

    /**
     * Réserver un créneau
     *
     * Confirme la demande du client sur un créneau qu'il a verrouillé (voir « Verrouiller un créneau ») avant l'expiration
     * du verrou. La réservation est créée au statut `en_attente` (le prestataire a 12 h pour répondre) et le créneau passe
     * à « réservé ». La réponse ne contient aucune donnée personnelle du prestataire ni code de réservation.
     *
     * @authenticated
     *
     * @response 201 {"id":"0198a1b2-0000-7000-8000-000000000060","statut":"en_attente","dateCreation":"2030-01-15T08:50:00+00:00","creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"prestataire":{"id":"0198a1b2-0000-7000-8000-000000000030","nomEntreprise":"Garage Exemple","quartier":"Keur Massar Nord","adresse":"Rue 10, Keur Massar"}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Créneau introuvable."}
     * @response 409 {"message":"Délai dépassé. Veuillez sélectionner de nouveau un créneau."}
     * @response 422 {"message":"Cette prestation n'est pas proposée sur ce créneau.","errors":{"prestation_id":["Cette prestation n'est pas proposée sur ce créneau."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function store(CreerReservationRequest $requete): JsonResponse|ReservationResource
    {
        try {
            $reservation = $this->service->creer($requete->user(), $requete->validated('creneau_id'), $requete->validated('prestation_id'));
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }

        return (new ReservationResource($reservation))->response()->setStatusCode(201);
    }

    /**
     * Mes réservations
     *
     * Paginée (20 par page), de la plus récente à la plus ancienne. Filtre optionnel `statut`. Le `codeReservation` n'existe
     * qu'après le paiement (`null` avant) et n'est jamais visible que de son client. `peutAnnuler` applique les règles
     * de l'annulation.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"en_attente","dateCreation":"2030-01-15T08:50:00+00:00","motifRefus":null,"codeReservation":null,"peutAnnuler":true,"creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"prestataire":{"id":"0198a1b2-0000-7000-8000-000000000030","nomEntreprise":"Garage Exemple","quartier":"Keur Massar Nord","adresse":"Rue 10, Keur Massar"}}],"links":{"first":"https://api.exemple.test/api/v1/reservations?page=1","last":"https://api.exemple.test/api/v1/reservations?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le statut doit être l'un de : en_attente, acceptee, confirmee, en_cours, terminee, refusee, annulee.","errors":{"statut":["Le statut doit être l'un de : en_attente, acceptee, confirmee, en_cours, terminee, refusee, annulee."]}}
     */
    public function index(ListerMesReservationsRequest $requete)
    {
        return ReservationClientResource::collection($this->suivi->lister($requete->user(), $requete->statut()));
    }

    /**
     * Détail d'une réservation
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000060","statut":"en_attente","dateCreation":"2030-01-15T08:50:00+00:00","motifRefus":null,"codeReservation":null,"peutAnnuler":true,"creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"prestataire":{"id":"0198a1b2-0000-7000-8000-000000000030","nomEntreprise":"Garage Exemple","quartier":"Keur Massar Nord","adresse":"Rue 10, Keur Massar"}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Réservation introuvable."}
     */
    public function show(Request $requete, string $id): JsonResponse|ReservationClientResource
    {
        $reservation = $this->suivi->trouver($requete->user(), $id);

        return $reservation === null
            ? response()->json(['message' => 'Réservation introuvable.'], Response::HTTP_NOT_FOUND)
            : new ReservationClientResource($reservation);
    }

    /**
     * Annuler une réservation
     *
     * Possible pour une réservation `en_attente`, `acceptee` ou `confirmee`, jusqu'à 2 h avant le début du créneau
     * (borne incluse). Le créneau est libéré. L'acompte éventuellement payé n'est pas remboursé. Aucun corps.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000060","statut":"annulee","dateCreation":"2030-01-15T08:50:00+00:00","motifRefus":null,"codeReservation":null,"peutAnnuler":false,"creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"prestataire":{"id":"0198a1b2-0000-7000-8000-000000000030","nomEntreprise":"Garage Exemple","quartier":"Keur Massar Nord","adresse":"Rue 10, Keur Massar"}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Réservation introuvable."}
     * @response 409 {"message":"L'annulation n'est plus possible moins de 2 heures avant le créneau."}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function annuler(Request $requete, string $id): JsonResponse|ReservationClientResource
    {
        try {
            return new ReservationClientResource($this->suivi->annuler($requete->user(), $id));
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }
    }
}
