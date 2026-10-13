<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\ListeNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Services\ConsultationNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Notifications
 *
 * Notifications reçues dans l'application (jeton Sanctum + rôle `client` ou `prestataire`). Chacun ne voit que les siennes.
 * L'administrateur ne reçoit aucune notification : il est alerté par les bannières de son tableau de bord (403 sur ces routes).
 * Les e-mails et les push partent séparément ; les textes ne contiennent jamais de code de réservation, de montant ni de téléphone.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly ConsultationNotificationService $notifications) {}

    /**
     * Mes notifications
     *
     * Paginée (20 par page), de la plus récente à la plus ancienne. `nonLues=true` ne renvoie que les notifications non lues.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000a01","type":"reservation_acceptee","titre":"Demande acceptée","corps":"Garage Sow a accepté votre demande. Payez l'acompte dans les 2 heures pour confirmer.","donnees":{"reservationId":"0198a1b2-0000-7000-8000-000000000060"},"lue":false,"lueLe":null,"dateCreation":"2030-01-13T10:00:00+00:00"}],"links":{"first":"https://api.exemple.test/api/v1/notifications?page=1","last":"https://api.exemple.test/api/v1/notifications?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le filtre « non lues » doit être vrai ou faux.","errors":{"nonLues":["Le filtre « non lues » doit être vrai ou faux."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function index(ListeNotificationsRequest $requete)
    {
        return NotificationResource::collection($this->notifications->lister($requete->user(), $requete->boolean('nonLues')));
    }

    /**
     * Nombre de notifications non lues
     *
     * @authenticated
     *
     * @response 200 {"nonLues":3}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function compteur(Request $requete): JsonResponse
    {
        return response()->json(['nonLues' => $this->notifications->compterNonLues($requete->user())]);
    }

    /**
     * Marquer une notification comme lue
     *
     * Sans effet si elle est déjà lue (la date de première lecture est conservée). La notification d'un autre utilisateur répond 404.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la notification. Example: 0198a1b2-0000-7000-8000-000000000a01
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000a01","type":"reservation_acceptee","titre":"Demande acceptée","corps":"Garage Sow a accepté votre demande. Payez l'acompte dans les 2 heures pour confirmer.","donnees":{"reservationId":"0198a1b2-0000-7000-8000-000000000060"},"lue":true,"lueLe":"2030-01-13T10:05:00+00:00","dateCreation":"2030-01-13T10:00:00+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Notification introuvable."}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function lire(Request $requete, string $id): JsonResponse|NotificationResource
    {
        try {
            return new NotificationResource($this->notifications->marquerLue($requete->user(), $id));
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }
    }

    /**
     * Tout marquer comme lu
     *
     * @authenticated
     *
     * @response 200 {"marquees":3}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function lireTout(Request $requete): JsonResponse
    {
        return response()->json(['marquees' => $this->notifications->marquerToutesLues($requete->user())]);
    }
}
