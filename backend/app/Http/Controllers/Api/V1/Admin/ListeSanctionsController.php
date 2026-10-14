<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListeSanctionsRequest;
use App\Http\Resources\SanctionAdminResource;
use App\Services\SanctionsAdminService;

/**
 * @group Administration — sanctions
 */
class ListeSanctionsController extends Controller
{
    public function __construct(private readonly SanctionsAdminService $service) {}

    /**
     * Liste de toutes les sanctions
     *
     * Toutes les sanctions de la plateforme, levées ou non, du plus récent au plus ancien (20 par page). Filtres combinables :
     * `actives`, `type`, `utilisateur`, `echeanceDansHeures` (suspensions non levées qui se terminent dans les N prochaines heures :
     * point d'arrivée de l'alerte `suspensions_a_echeance`). L'historique d'un seul compte reste sur `/admin/utilisateurs/{id}/sanctions`.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000040","type":"suspension_temporaire","utilisateur":{"id":"0198a1b2-0000-7000-8000-000000000002","prenom":"Moussa","nom":"Diop","role":"prestataire"},"motif":"Comportement contraire aux règles.","litigeId":null,"avisId":null,"dateCreation":"2030-01-20T09:00:00+00:00","dateFin":"2030-01-27T09:00:00+00:00","active":true,"leveeLe":null}],"links":{"first":"https://api.exemple.test/api/v1/admin/sanctions?page=1","last":"https://api.exemple.test/api/v1/admin/sanctions?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le type doit être avertissement, suspension_temporaire ou bannissement_definitif.","errors":{"type":["Le type doit être avertissement, suspension_temporaire ou bannissement_definitif."]}}
     */
    public function __invoke(ListeSanctionsRequest $requete)
    {
        return SanctionAdminResource::collection($this->service->lister($requete->filtres()));
    }
}
