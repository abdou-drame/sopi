<?php

namespace App\Http\Controllers\Api\V1\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prestataire\ConfigurerAgendaRequest;
use App\Http\Requests\Prestataire\ConsulterAgendaRequest;
use App\Http\Requests\Prestataire\DeclarerIndisponibiliteRequest;
use App\Http\Resources\CreneauResource;
use App\Models\Prestataire;
use App\Services\AgendaService;
use App\Services\IndisponibiliteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as ReponseVide;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Prestataire — agenda
 *
 * Routes réservées aux prestataires (jeton Sanctum + rôle `prestataire`). L'agenda est fait de créneaux ;
 * les dates et heures sont celles du fuseau Africa/Dakar. Un prestataire ne voit et ne modifie que son propre agenda.
 */
class AgendaController extends Controller
{
    public function __construct(private readonly AgendaService $service, private readonly IndisponibiliteService $indisponibilites) {}

    /**
     * Configurer l'agenda
     *
     * Génère des créneaux LIBRES, consécutifs, de `duree_creneau_minutes` entre `heure_debut` et `heure_fin` pour chaque plage,
     * puis aux mêmes jours de la semaine pendant `repeter_semaines` semaines supplémentaires.
     * Tout ou rien : si un créneau en chevauche un autre (existant, quel que soit son statut, ou de la même requête), rien n'est créé (422).
     * Limites : 7 plages, créneaux de 15 à 480 minutes, 8 semaines de répétition, 500 créneaux par requête.
     * La liste `creneaux` n'est renvoyée que si elle compte 200 créneaux au plus.
     *
     * @authenticated
     *
     * @response 201 {"nombreCreneaux":2,"creneaux":[{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-15T09:00:00+00:00","dateFin":"2030-01-15T10:00:00+00:00","statut":"libre"},{"id":"0198a1b2-0000-7000-8000-000000000041","dateDebut":"2030-01-15T10:00:00+00:00","dateFin":"2030-01-15T11:00:00+00:00","statut":"libre"}]}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le créneau du 15/01/2030 de 09:00 à 10:00 chevauche un créneau existant (09:30 à 10:30).","errors":{"plages":["Le créneau du 15/01/2030 de 09:00 à 10:00 chevauche un créneau existant (09:30 à 10:30)."]}}
     */
    public function configurer(ConfigurerAgendaRequest $request): JsonResponse
    {
        $creneaux = $this->service->configurer($this->prestataire($request), array_values($request->validated('plages')));

        $reponse = ['nombreCreneaux' => $creneaux->count()];
        if ($creneaux->count() <= AgendaService::LISTE_MAX) {
            $reponse['creneaux'] = CreneauResource::collection($creneaux)->resolve($request);
        }

        return response()->json($reponse, Response::HTTP_CREATED);
    }

    /**
     * Mon agenda
     *
     * Mes créneaux dont le début tombe entre `du` et `au` (inclus), triés par début, sans pagination.
     * Par défaut : d'aujourd'hui à 30 jours plus tard. Intervalle maximal : 62 jours.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-15T09:00:00+00:00","dateFin":"2030-01-15T10:00:00+00:00","statut":"libre"}]}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"L'intervalle ne peut pas dépasser 62 jours.","errors":{"au":["L'intervalle ne peut pas dépasser 62 jours."]}}
     */
    public function consulter(ConsulterAgendaRequest $request)
    {
        [$du, $au] = $request->intervalle();

        return CreneauResource::collection($this->service->consulter($this->prestataire($request), $du, $au));
    }

    /**
     * Supprimer un créneau libre
     *
     * Uniquement si le créneau est libre. Le créneau d'un autre prestataire répond 404, comme un créneau inexistant.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du créneau. Example: 0198a1b2-0000-7000-8000-000000000040
     *
     * @response 204
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Créneau introuvable."}
     * @response 409 {"message":"Ce créneau n'est plus libre."}
     */
    public function supprimerCreneau(Request $request, string $id): JsonResponse|ReponseVide
    {
        return match ($this->service->supprimer($this->prestataire($request), $id)) {
            true => response()->noContent(),
            false => response()->json(['message' => 'Ce créneau n\'est plus libre.'], Response::HTTP_CONFLICT),
            null => response()->json(['message' => 'Créneau introuvable.'], Response::HTTP_NOT_FOUND),
        };
    }

    /**
     * Déclarer une indisponibilité
     *
     * Bloque immédiatement (statut `indisponible`) les créneaux libres qui chevauchent la plage, en une seule opération atomique.
     * Un créneau verrouillé dont le verrou a expiré est bloqué ; un créneau réservé ou verrouillé avec un verrou valide n'est pas modifié,
     * il est seulement compté pour être traité plus tard (annulation, remboursement et notification : pas encore disponibles).
     * Idempotent. Plage de 90 jours au maximum. Il n'existe pas encore de route pour lever une indisponibilité.
     *
     * @authenticated
     *
     * @response 200 {"creneauxBloques":4,"creneauxReserves":1,"creneauxVerrouilles":0}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"La fin doit être après le début.","errors":{"fin":["La fin doit être après le début."]}}
     */
    public function declarerIndisponibilite(DeclarerIndisponibiliteRequest $request): JsonResponse
    {
        [$debut, $fin] = $request->plage();
        $bilan = $this->indisponibilites->declarer($this->prestataire($request), $debut, $fin);

        return response()->json([
            'creneauxBloques' => $bilan['bloques'],
            'creneauxReserves' => $bilan['reserves'],
            'creneauxVerrouilles' => $bilan['verrouilles'],
        ]);
    }

    private function prestataire(Request $request): Prestataire
    {
        return $request->user()->prestataire ?? throw new AccessDeniedHttpException;
    }
}
