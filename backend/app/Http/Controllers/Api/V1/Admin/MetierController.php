<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreerMetierRequest;
use App\Http\Requests\Admin\ModifierMetierRequest;
use App\Http\Resources\MetierAdminResource;
use App\Services\MetierService;
use Illuminate\Http\JsonResponse;

/**
 * @group Administration — métiers
 *
 * Routes réservées aux administrateurs (jeton Sanctum + rôle `administrateur`). Les métiers sont proposés à l'inscription des
 * prestataires et à la recherche. Un métier n'est jamais supprimé : on le désactive (il n'est plus proposé à l'inscription, ses
 * prestataires le gardent). Les créations et modifications sont limitées à 10 requêtes par minute.
 */
class MetierController extends Controller
{
    public function __construct(private readonly MetierService $service) {}

    /**
     * Liste des métiers
     *
     * Tous les métiers (actifs ou non), par libellé, sans pagination, avec le nombre de prestataires rattachés.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000090","code":"coiffure","libelle":"Coiffure","actif":true,"nombrePrestataires":4,"dateCreation":"2030-01-01T09:00:00+00:00"},{"id":"0198a1b2-0000-7000-8000-000000000091","code":"mecanique","libelle":"Mécanique","actif":true,"nombrePrestataires":7,"dateCreation":"2030-01-01T09:00:00+00:00"}]}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function index()
    {
        return MetierAdminResource::collection($this->service->lister());
    }

    /**
     * Ajouter un métier
     *
     * Seul le nom est saisi ; le code est déduit (minuscules, sans accent ni espace, 20 caractères au plus). Un nom dont le code existe
     * déjà répond 422.
     *
     * @authenticated
     *
     * @response 201 {"id":"0198a1b2-0000-7000-8000-000000000092","code":"plomberie","libelle":"Plomberie","actif":true,"nombrePrestataires":0,"dateCreation":"2030-01-20T09:00:00+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 409 {"message":"Un métier portant ce nom existe déjà."}
     * @response 422 {"message":"Un métier portant ce nom existe déjà.","errors":{"libelle":["Un métier portant ce nom existe déjà."]}}
     */
    public function store(CreerMetierRequest $requete): JsonResponse
    {
        return $this->repondre(fn () => $this->service->creer($requete->libelle()), 201);
    }

    /**
     * Modifier un métier
     *
     * Renomme le métier et/ou l'active ou le désactive. Le dernier métier actif ne peut pas être désactivé (409).
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du métier. Example: 0198a1b2-0000-7000-8000-000000000092
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000092","code":"plomberie","libelle":"Plomberie et chauffage","actif":false,"nombrePrestataires":0,"dateCreation":"2030-01-20T09:00:00+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Métier introuvable."}
     * @response 409 {"message":"Au moins un métier doit rester actif."}
     * @response 422 {"message":"Indiquez le nouveau nom ou l'état du métier.","errors":{"libelle":["Indiquez le nouveau nom ou l'état du métier."]}}
     */
    public function update(ModifierMetierRequest $requete, string $id): JsonResponse
    {
        return $this->repondre(fn () => $this->service->modifier($id, $requete->libelle(), $requete->actif()));
    }

    private function repondre(callable $action, int $statut = 200): JsonResponse
    {
        try {
            return (new MetierAdminResource($action()))->response()->setStatusCode($statut);
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }
    }
}
