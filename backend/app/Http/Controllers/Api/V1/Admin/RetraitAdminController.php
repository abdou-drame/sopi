<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListeRetraitsRequest;
use App\Http\Resources\RetraitAdminResource;
use App\Services\RetraitsAdminService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Administration — retraits
 *
 * Routes de lecture réservées aux administrateurs (jeton Sanctum + rôle `administrateur`). Montants en FCFA entiers.
 */
class RetraitAdminController extends Controller
{
    public function __construct(private readonly RetraitsAdminService $service) {}

    /**
     * Liste des retraits
     *
     * Retraits des prestataires, du plus récent au plus ancien (20 par page). Filtres combinables : `statut`, `prestataire`
     * (identifiant du profil prestataire) et `bloques` (retraits en_cours depuis plus longtemps que le délai de blocage : point
     * d'arrivée de l'alerte `retraits_bloques`). Le téléphone de versement est masqué dans la liste ; la référence DexPay n'apparaît
     * que dans le détail.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000080","prestataire":{"id":"0198a1b2-0000-7000-8000-000000000012","userId":"0198a1b2-0000-7000-8000-000000000002","nomEntreprise":"Garage Exemple"},"montant":5000,"statut":"en_cours","bloque":true,"dateDemande":"2030-01-20T08:00:00+00:00","traiteLe":null,"recredite":false,"telephoneVersement":"+221*******67"}],"links":{"first":"https://api.exemple.test/api/v1/admin/retraits?page=1","last":"https://api.exemple.test/api/v1/admin/retraits?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le statut doit être en_cours, reussi ou echoue.","errors":{"statut":["Le statut doit être en_cours, reussi ou echoue."]}}
     */
    public function index(ListeRetraitsRequest $requete)
    {
        return RetraitAdminResource::collection($this->service->lister($requete->filtres()));
    }

    /**
     * Détail d'un retrait
     *
     * Prestataire, montant, statut, dates de demande et de traitement, téléphone de versement complet, référence DexPay et
     * `recredite` / `recrediteLe` (le montant d'un retrait échoué a-t-il été remis au solde).
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du retrait. Example: 0198a1b2-0000-7000-8000-000000000080
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000080","prestataire":{"id":"0198a1b2-0000-7000-8000-000000000012","userId":"0198a1b2-0000-7000-8000-000000000002","nomEntreprise":"Garage Exemple"},"montant":5000,"statut":"echoue","bloque":false,"dateDemande":"2030-01-20T08:00:00+00:00","traiteLe":"2030-01-20T08:05:00+00:00","recredite":true,"telephoneVersement":"+221771234567","referenceDexpay":"DXP-RET-EXEMPLE","recrediteLe":"2030-01-20T08:05:00+00:00"}
     * @response 404 {"message":"Retrait introuvable."}
     */
    public function show(string $id): JsonResponse|RetraitAdminResource
    {
        $retrait = $this->service->trouver($id);

        return $retrait === null
            ? response()->json(['message' => 'Retrait introuvable.'], Response::HTTP_NOT_FOUND)
            : RetraitAdminResource::detail($retrait);
    }
}
