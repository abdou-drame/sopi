<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListeNotificationsEnEchecRequest;
use App\Http\Resources\NotificationEnEchecAdminResource;
use App\Services\NotificationsEnEchecAdminService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Administration — notifications en échec
 *
 * Routes de lecture réservées aux administrateurs (jeton Sanctum + rôle `administrateur`). Le contenu du message et les coordonnées
 * du destinataire ne sont jamais exposés.
 */
class NotificationEnEchecController extends Controller
{
    public function __construct(private readonly NotificationsEnEchecAdminService $service) {}

    /**
     * Liste des notifications en échec
     *
     * Notifications dont au moins un canal (e-mail ou push) a échoué définitivement : point d'arrivée de l'alerte
     * `notifications_en_echec`. Du plus récent au plus ancien (20 par page). Filtres combinables : `canal`, `type`, `ancienneteMinutes`.
     * Chaque ligne donne le type, le destinataire (id et rôle), les canaux en échec, le nombre d'essais, la classe de l'erreur et la date.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000090","type":"reservation_confirmee","destinataire":{"id":"0198a1b2-0000-7000-8000-000000000001","role":"client"},"canauxEnEchec":["email"],"tentatives":5,"classeErreur":"TransportException","dateCreation":"2030-01-20T08:00:00+00:00"}],"links":{},"meta":{"current_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le canal doit être email ou push.","errors":{"canal":["Le canal doit être email ou push."]}}
     */
    public function index(ListeNotificationsEnEchecRequest $requete)
    {
        return NotificationEnEchecAdminResource::collection($this->service->lister($requete->filtres()));
    }

    /**
     * Détail d'une notification en échec
     *
     * Même forme qu'une ligne de la liste. Permet d'ouvrir un identifiant renvoyé par l'alerte `notifications_en_echec`, quelle que
     * soit sa page dans la liste. Une notification qui n'est pas (ou plus) en échec répond 404.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la notification. Example: 0198a1b2-0000-7000-8000-000000000090
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000090","type":"reservation_confirmee","destinataire":{"id":"0198a1b2-0000-7000-8000-000000000001","role":"client"},"canauxEnEchec":["email"],"tentatives":5,"classeErreur":"TransportException","dateCreation":"2030-01-20T08:00:00+00:00"}
     * @response 404 {"message":"Notification introuvable."}
     */
    public function show(string $id): JsonResponse|NotificationEnEchecAdminResource
    {
        $notification = $this->service->trouver($id);

        return $notification === null
            ? response()->json(['message' => 'Notification introuvable.'], Response::HTTP_NOT_FOUND)
            : new NotificationEnEchecAdminResource($notification);
    }
}
