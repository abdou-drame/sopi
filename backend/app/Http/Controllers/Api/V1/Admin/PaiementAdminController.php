<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListePaiementsRequest;
use App\Http\Resources\PaiementAdminResource;
use App\Services\PaiementsAdminService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Administration — paiements
 *
 * Routes de lecture réservées aux administrateurs (jeton Sanctum + rôle `administrateur`). Montants en FCFA entiers.
 */
class PaiementAdminController extends Controller
{
    public function __construct(private readonly PaiementsAdminService $service) {}

    /**
     * Liste des paiements
     *
     * Paiements d'acompte, du plus récent au plus ancien (20 par page). Filtres combinables : `statut`, `remboursementDu`,
     * `retardRemboursementMinutes` (remboursements dus depuis au moins N minutes, essais épuisés compris : point d'arrivée des alertes
     * `remboursements_en_retard` et `remboursements_en_echec`) et `reservation`. La référence DexPay n'apparaît que dans le détail.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000060","reservationId":"0198a1b2-0000-7000-8000-000000000020","statut":"reussi","montantAcompte":3000,"commission":1000,"remboursementDu":true,"remboursementDuLe":"2030-01-20T10:00:00+00:00","tentativesRemboursement":3,"tentativesEpuisees":false,"payeLe":"2030-01-20T09:00:00+00:00","rembourseLe":null,"crediteLe":null}],"links":{"first":"https://api.exemple.test/api/v1/admin/paiements?page=1","last":"https://api.exemple.test/api/v1/admin/paiements?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le statut doit être en_attente, reussi, echoue ou rembourse.","errors":{"statut":["Le statut doit être en_attente, reussi, echoue ou rembourse."]}}
     */
    public function index(ListePaiementsRequest $requete)
    {
        return PaiementAdminResource::collection($this->service->lister($requete->filtres()));
    }

    /**
     * Détail d'un paiement
     *
     * Réservation concernée, acompte, commission, statut, dates (dont `crediteLe`, crédit du prestataire), remboursement dû ou non,
     * tentatives et leur maximum, et référence DexPay (`null` tant que la session n'a pas été créée).
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du paiement. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000060","reservationId":"0198a1b2-0000-7000-8000-000000000020","statut":"reussi","montantAcompte":3000,"commission":1000,"remboursementDu":true,"remboursementDuLe":"2030-01-20T10:00:00+00:00","tentativesRemboursement":10,"tentativesEpuisees":true,"payeLe":"2030-01-20T09:00:00+00:00","rembourseLe":null,"crediteLe":null,"reservation":{"id":"0198a1b2-0000-7000-8000-000000000020","statut":"annulee","prixApplique":10000,"clientId":"0198a1b2-0000-7000-8000-000000000001","prestataireId":"0198a1b2-0000-7000-8000-000000000012"},"creeLe":"2030-01-20T08:45:00+00:00","tentativesMaximum":10,"referenceDexpay":"DXP-EXEMPLE"}
     * @response 404 {"message":"Paiement introuvable."}
     */
    public function show(string $id): JsonResponse|PaiementAdminResource
    {
        $paiement = $this->service->trouver($id);

        return $paiement === null
            ? response()->json(['message' => 'Paiement introuvable.'], Response::HTTP_NOT_FOUND)
            : PaiementAdminResource::detail($paiement);
    }
}
