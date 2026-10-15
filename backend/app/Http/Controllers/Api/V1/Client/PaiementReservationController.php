<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementReservationResource;
use App\Services\PaiementReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Client — paiement
 *
 * Routes réservées aux clients (jeton Sanctum + rôle `client`), limitées à 10 requêtes par minute et par utilisateur.
 */
class PaiementReservationController extends Controller
{
    public function __construct(private readonly PaiementReservationService $service) {}

    /**
     * Payer l'acompte d'une réservation
     *
     * Pour une réservation `acceptee` dont le délai de paiement (2 h après l'acceptation) n'est pas dépassé, crée le paiement de
     * l'acompte (30 % du prix) et renvoie l'adresse de la page de paiement. Aucun corps : le montant est toujours calculé par le
     * serveur à partir du prix convenu, figé dans la réservation à sa création. La réservation reste `acceptee` : elle ne sera confirmée (et son code
     * généré) qu'à la confirmation du paiement par le prestataire de paiement. Idempotent : si un paiement est déjà en cours
     * pour cette réservation, le même paiement et la même adresse sont renvoyés avec le code 200.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 201 {"paiement":{"id":"0198a1b2-0000-7000-8000-000000000070","statut":"en_attente","montantAcompte":"4500.00","devise":"XOF"},"montantRestantSurPlace":"10500.00","urlPaiement":"https://paiement.exemple.test/session/abc123","reservation":{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"acceptee"}}
     * @response 200 {"paiement":{"id":"0198a1b2-0000-7000-8000-000000000070","statut":"en_attente","montantAcompte":"4500.00","devise":"XOF"},"montantRestantSurPlace":"10500.00","urlPaiement":"https://paiement.exemple.test/session/abc123","reservation":{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"acceptee"}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Réservation introuvable."}
     * @response 409 {"message":"Le délai de paiement est dépassé."}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     * @response 503 {"message":"Le service de paiement est momentanément indisponible. Veuillez réessayer."}
     */
    public function __invoke(Request $requete, string $id): JsonResponse
    {
        try {
            [$paiement, $cree] = $this->service->initier($requete->user(), $id);
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()] + ($e->diagnostic !== null ? ['diagnostic' => $e->diagnostic] : []), $e->statut);
        }

        return (new PaiementReservationResource($paiement))->response()->setStatusCode($cree ? 201 : 200);
    }
}
