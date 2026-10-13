<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevenusRequest;
use App\Services\RevenusAdminService;
use Illuminate\Http\JsonResponse;

/**
 * @group Administration — tableau de bord
 */
class RevenusController extends Controller
{
    public function __construct(private readonly RevenusAdminService $revenus) {}

    /**
     * Revenus de la plateforme
     *
     * Série par jour, semaine (lundi à dimanche) ou mois calendaire, sur une période de 366 jours au plus (bornes incluses, fuseau
     * Africa/Dakar). Par défaut : les 30 derniers jours, par jour. Les périodes vides sont présentes avec des zéros.
     * Définitions : **revenus** = commissions des paiements réussis dont le crédit au prestataire est fait (prestation terminée), datées
     * par ce crédit, paiements remboursés exclus ; **acomptesEncaisses** = acomptes des paiements réussis, datés par le paiement ;
     * **remboursements** = acomptes remboursés, datés par le remboursement ; **retraitsVerses** = retraits réussis, datés par leur traitement.
     * **soldePrestataires** est le cumul des soldes à l'instant de la demande. Montants en FCFA entiers.
     *
     * @authenticated
     *
     * @response 200 {"dateGeneration":"2030-01-20T10:00:00+00:00","periode":{"debut":"2030-01-01","fin":"2030-01-10","regroupement":"semaine","fuseau":"Africa/Dakar"},"totaux":{"revenus":42000,"acomptesEncaisses":150000,"remboursements":9000,"retraitsVerses":60000},"soldePrestataires":84000,"series":[{"debut":"2030-01-01","fin":"2030-01-06","revenus":30000,"acomptesEncaisses":90000,"remboursements":0,"retraitsVerses":60000},{"debut":"2030-01-07","fin":"2030-01-10","revenus":12000,"acomptesEncaisses":60000,"remboursements":9000,"retraitsVerses":0}]}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"La période ne peut pas dépasser 366 jours.","errors":{"fin":["La période ne peut pas dépasser 366 jours."]}}
     */
    public function index(RevenusRequest $requete): JsonResponse
    {
        return response()->json($this->revenus->revenus($requete->debut(), $requete->fin(), $requete->regroupement()));
    }
}
