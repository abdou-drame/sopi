<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AlertesAdminService;
use Illuminate\Http\JsonResponse;

/**
 * @group Administration — tableau de bord
 */
class AlertesController extends Controller
{
    public function __construct(private readonly AlertesAdminService $alertes) {}

    /**
     * Alertes
     *
     * Éléments qui demandent l'attention de l'administrateur, calculés à la demande (rien n'est envoyé : l'administrateur ne reçoit ni
     * e-mail ni notification push). Liste vide quand tout est calme. Triées de la plus grave à la moins grave. Types : `dossiers_en_attente`,
     * `litiges_ouverts`, `avis_signales`, `retraits_bloques`, `remboursements_en_echec`, `remboursements_en_retard`,
     * `notifications_en_echec`, `suspensions_a_echeance`. Gravités : `info`, `attention`, `critique` (seuils configurables, `sopi.admin.alertes.*`).
     * `ids` : au plus 20 identifiants, les plus anciens d'abord, pour ouvrir les éléments (le dossier est identifié par l'id du prestataire) ;
     * `compteur` donne le total réel. `ressource` : où ouvrir les `ids` dans l'administration (`demandes-prestataires` : `/admin/prestataires/{id}`, `litiges`, `retraits`, `paiements`, `notifications` : `/admin/notifications-en-echec/{id}` ; `avis` : liste `/admin/avis?aTraiter=1` ; `sanctions` : liste `/admin/sanctions?echeanceDansHeures=24`). `ageMinutes` : ancienneté du plus ancien élément ; `echeanceDansMinutes` : délai avant la première fin de suspension.
     *
     * @authenticated
     *
     * @response 200 {"dateGeneration":"2030-01-20T10:00:00+00:00","total":2,"parGravite":{"critique":1,"attention":0,"info":1},"alertes":[{"type":"litiges_ouverts","gravite":"critique","compteur":2,"message":"2 litiges ouverts sont à traiter.","ids":["0198a1b2-0000-7000-8000-000000000020","0198a1b2-0000-7000-8000-000000000021"],"ageMinutes":4500,"echeanceDansMinutes":null},{"type":"suspensions_a_echeance","gravite":"info","compteur":1,"message":"1 suspension temporaire se termine bientôt.","ids":["0198a1b2-0000-7000-8000-000000000030"],"ageMinutes":null,"echeanceDansMinutes":600}]}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function index(): JsonResponse
    {
        return response()->json($this->alertes->alertes());
    }
}
