<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\DeposerAvisRequest;
use App\Http\Resources\AvisClientResource;
use App\Services\AvisService;
use Illuminate\Http\JsonResponse;

/**
 * @group Client — avis
 *
 * Routes réservées aux clients (jeton Sanctum + rôle `client`), limitées à 10 requêtes par minute et par utilisateur.
 */
class AvisReservationController extends Controller
{
    public function __construct(private readonly AvisService $service) {}

    /**
     * Donner son avis sur une réservation
     *
     * Réservé au client de la réservation, une fois la prestation terminée (`terminee`). Un seul avis par réservation, qui ne
     * peut être ni modifié ni retiré. La note est un entier de 1 à 5 ; le commentaire est facultatif (3 à 1000 caractères).
     * Le prestataire noté est toujours celui de la réservation. Une réservation inconnue ou appartenant à un autre client
     * répond 404.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 201 {"note":4,"commentaire":"Travail soigné et ponctuel.","dateCreation":"2030-01-17T10:00:00+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Réservation introuvable."}
     * @response 409 {"message":"Vous avez déjà donné votre avis pour cette réservation."}
     * @response 422 {"message":"La note doit être comprise entre 1 et 5.","errors":{"note":["La note doit être comprise entre 1 et 5."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function store(DeposerAvisRequest $requete, string $id): JsonResponse
    {
        try {
            $avis = $this->service->deposer($requete->user(), $id, (int) $requete->validated('note'), $requete->commentaire());
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }

        return (new AvisClientResource($avis))->response()->setStatusCode(201);
    }
}
