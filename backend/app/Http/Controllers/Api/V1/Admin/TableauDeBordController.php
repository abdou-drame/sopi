<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\StatistiquesAdminService;
use Illuminate\Http\JsonResponse;

/**
 * @group Administration — tableau de bord
 *
 * Routes réservées aux administrateurs (jeton Sanctum + rôle `administrateur`). Lecture seule : rien n'est modifié ni envoyé.
 * Les agrégats sont calculés à chaque appel par des requêtes groupées (nombre de requêtes constant, quel que soit le volume).
 * Aucune donnée personnelle n'est renvoyée : seulement des compteurs et des montants globaux en FCFA (entiers).
 */
class TableauDeBordController extends Controller
{
    public function __construct(private readonly StatistiquesAdminService $statistiques) {}

    /**
     * Statistiques générales
     *
     * Comptes (clients et prestataires par statut, dossiers en attente), réservations (total, par statut, par domaine de métier et activité
     * des 7 et 30 derniers jours glissants), paiements (nombre et montant d'acompte par statut), litiges, avis (dont signalés à modérer,
     * note moyenne des avis non masqués) et sanctions actives. Les réservations « terminées » d'une fenêtre sont datées par leur passage
     * à TERMINEE. Les fenêtres et les dates sont interprétées dans le fuseau de l'application (Africa/Dakar).
     *
     * @authenticated
     *
     * @response 200 {"dateGeneration":"2030-01-20T10:00:00+00:00","comptes":{"clients":{"total":120,"parStatut":{"en_attente":0,"actif":115,"refuse":0,"suspendu":3,"banni":2}},"prestataires":{"total":18,"parStatut":{"en_attente":2,"actif":14,"refuse":1,"suspendu":1,"banni":0},"dossiersEnAttente":2},"administrateurs":1},"reservations":{"total":340,"parStatut":{"en_attente":4,"acceptee":2,"confirmee":10,"en_cours":1,"terminee":280,"refusee":15,"annulee":28},"parDomaine":{"mecanique":{"total":150,"parStatut":{"en_attente":2,"acceptee":1,"confirmee":5,"en_cours":1,"terminee":130,"refusee":5,"annulee":6}},"coiffure":{"total":120,"parStatut":{"en_attente":2,"acceptee":1,"confirmee":4,"en_cours":0,"terminee":100,"refusee":6,"annulee":7}},"pressing":{"total":70,"parStatut":{"en_attente":0,"acceptee":0,"confirmee":1,"en_cours":0,"terminee":50,"refusee":4,"annulee":15}}},"activiteRecente":{"derniers7Jours":{"creees":30,"terminees":25},"derniers30Jours":{"creees":110,"terminees":95}}},"paiements":{"total":{"nombre":300,"montant":1350000},"parStatut":{"en_attente":{"nombre":3,"montant":9000},"reussi":{"nombre":280,"montant":1260000},"echoue":{"nombre":7,"montant":31500},"rembourse":{"nombre":10,"montant":49500}}},"litiges":{"total":9,"ouverts":2,"clos":7},"avis":{"total":210,"signales":3,"masques":4,"noteMoyenne":4.35},"sanctions":{"suspensionsEnCours":1,"bannissements":2}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function index(): JsonResponse
    {
        return response()->json($this->statistiques->tableauDeBord());
    }
}
