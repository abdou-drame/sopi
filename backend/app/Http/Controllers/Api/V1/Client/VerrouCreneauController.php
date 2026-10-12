<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\VerrouCreneauResource;
use App\Services\VerrouCreneauService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as ReponseVide;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Client — verrou d'un créneau
 *
 * Routes réservées aux clients (jeton Sanctum + rôle `client`), limitées à 20 requêtes par minute et par utilisateur.
 * Un client sélectionne un créneau libre : il est verrouillé pour lui pendant 5 minutes, le temps de réserver.
 */
class VerrouCreneauController extends Controller
{
    public function __construct(private readonly VerrouCreneauService $service) {}

    /**
     * Verrouiller un créneau
     *
     * Verrouille le créneau pour 5 minutes si, et seulement si, il est libre et à venir (une seule opération atomique :
     * si deux clients tentent en même temps, un seul l'obtient). Un client ne détient qu'un verrou à la fois : verrouiller
     * un autre créneau libère le précédent. Si le client tient déjà un verrou valide sur ce créneau, la même échéance
     * est renvoyée, sans prolongation.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du créneau. Example: 0198a1b2-0000-7000-8000-000000000040
     *
     * @response 200 {"creneauId":"0198a1b2-0000-7000-8000-000000000040","verrouExpireA":"2030-01-15T08:55:00+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Créneau introuvable."}
     * @response 409 {"message":"Ce créneau n'est plus disponible."}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function verrouiller(Request $request, string $id): JsonResponse|VerrouCreneauResource
    {
        return match ($creneau = $this->service->verrouiller($request->user(), $id)) {
            null => response()->json(['message' => 'Créneau introuvable.'], Response::HTTP_NOT_FOUND),
            false => response()->json(['message' => 'Ce créneau n\'est plus disponible.'], Response::HTTP_CONFLICT),
            default => new VerrouCreneauResource($creneau),
        };
    }

    /**
     * Libérer son verrou
     *
     * Remet le créneau à libre, uniquement s'il est verrouillé par le client connecté.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du créneau. Example: 0198a1b2-0000-7000-8000-000000000040
     *
     * @response 204
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Verrou introuvable."}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function liberer(Request $request, string $id): JsonResponse|ReponseVide
    {
        return $this->service->liberer($request->user(), $id)
            ? response()->noContent()
            : response()->json(['message' => 'Verrou introuvable.'], Response::HTTP_NOT_FOUND);
    }
}
