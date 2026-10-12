<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CreerReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;

/**
 * @group Client — réservations
 *
 * Routes réservées aux clients (jeton Sanctum + rôle `client`), limitées à 20 requêtes par minute et par utilisateur.
 */
class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $service) {}

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
}
