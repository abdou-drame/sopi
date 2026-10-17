<?php

namespace App\Http\Controllers\Api\V1\Prestataire;

use App\Enums\OperateurRetrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prestataire\DemanderRetraitRequest;
use App\Http\Resources\MouvementPortefeuilleResource;
use App\Http\Resources\RetraitResource;
use App\Models\Prestataire;
use App\Services\RetraitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Prestataire — solde et retraits
 *
 * Routes réservées aux prestataires (jeton Sanctum + rôle `prestataire`). Un prestataire ne voit que son propre solde et ses
 * propres retraits ; le retrait d'un autre prestataire répond 404, comme un retrait inexistant. Les montants sont en FCFA.
 */
class RetraitController extends Controller
{
    public function __construct(private readonly RetraitService $service) {}

    /**
     * Mon solde
     *
     * Solde disponible et limites d'un retrait (en FCFA).
     *
     * @authenticated
     *
     * @response 200 {"solde":12500.0,"montantMinimumRetrait":1000,"montantMaximumRetrait":500000}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function solde(Request $request): JsonResponse
    {
        return response()->json([
            'solde' => (float) $this->prestataire($request)->fresh()->solde,
            'montantMinimumRetrait' => (int) config('sopi.retraits.montant_minimum'),
            'montantMaximumRetrait' => (int) config('sopi.retraits.montant_maximum'),
        ]);
    }

    /**
     * Mes retraits
     *
     * Historique paginé (20 par page), du plus récent au plus ancien. Le numéro de versement est masqué (3 derniers chiffres).
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000070","montant":5000.0,"statut":"reussi","operateur":"wave","telephoneVersement":"+221******567","dateDemande":"2030-01-15T08:50:00+00:00","dateTraitement":"2030-01-15T08:50:02+00:00"}],"links":{"first":"https://api.exemple.test/api/v1/prestataire/retraits?page=1","last":"https://api.exemple.test/api/v1/prestataire/retraits?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function index(Request $request)
    {
        return RetraitResource::collection($this->service->lister($this->prestataire($request)));
    }

    /**
     * Historique du portefeuille
     *
     * Mouvements du solde (crédit de fin de prestation, demande de retrait, recrédit d'un retrait échoué), paginés (20 par page),
     * du plus récent au plus ancien. `montant` est toujours positif : le sens se lit dans `type` (`credit` et `remboursement_retrait`
     * augmentent le solde, `retrait` le diminue). `soldeApres` est le solde juste après le mouvement (FCFA). `prestation` est le nom du service de la réservation liée (crédit), sinon `null` ; `operateur` (`wave` ou `orange_money`, `null` si inconnu) et `statutRetrait` (`en_cours`, `reussi` ou `echoue`) ne sont renseignés que pour un retrait.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000080","type":"credit","montant":3000.0,"libelle":"Prestation terminée (acompte moins commission)","soldeApres":8000.0,"reservationId":"0198a1b2-0000-7000-8000-000000000020","retraitId":null,"prestation":"Vidange","operateur":null,"statutRetrait":null,"date":"2030-01-15T08:50:00+00:00"}],"links":{"first":"https://api.exemple.test/api/v1/prestataire/portefeuille/mouvements?page=1","last":"https://api.exemple.test/api/v1/prestataire/portefeuille/mouvements?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function mouvements(Request $request)
    {
        return MouvementPortefeuilleResource::collection($this->service->mouvements($this->prestataire($request)));
    }

    /**
     * Détail d'un retrait
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du retrait. Example: 0198a1b2-0000-7000-8000-000000000070
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000070","montant":5000.0,"statut":"reussi","operateur":"wave","telephoneVersement":"+221******567","dateDemande":"2030-01-15T08:50:00+00:00","dateTraitement":"2030-01-15T08:50:02+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Retrait introuvable."}
     */
    public function show(Request $request, string $id): JsonResponse|RetraitResource
    {
        $retrait = $this->service->trouver($this->prestataire($request), $id);

        return $retrait === null
            ? response()->json(['message' => 'Retrait introuvable.'], Response::HTTP_NOT_FOUND)
            : new RetraitResource($retrait);
    }

    /**
     * Demander un retrait
     *
     * Débite immédiatement le solde puis demande le versement au prestataire de paiement (Wave ou Orange Money). Le montant est un
     * entier de FCFA entre le minimum et le maximum (voir `/prestataire/solde`). Limité à 5 demandes par minute.
     * `operateur` (obligatoire) : `wave` ou `orange_money`. Statuts renvoyés : `reussi` (versement terminé), ou `en_cours` :
     * versement accepté mais pas encore terminé, ou résultat incertain (délai dépassé, panne). Le montant reste alors débité, sans
     * nouveau débit : le retrait passe à `reussi` ou `echoue` (montant recrédité une seule fois) à la confirmation du prestataire de
     * paiement. Un refus définitif répond 503 : le solde est recrédité (une seule fois) et la demande peut être refaite.
     *
     * @authenticated
     *
     * @response 201 {"id":"0198a1b2-0000-7000-8000-000000000070","montant":5000.0,"statut":"reussi","operateur":"wave","telephoneVersement":"+221******567","dateDemande":"2030-01-15T08:50:00+00:00","dateTraitement":"2030-01-15T08:50:02+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Solde insuffisant.","errors":{"montant":["Solde insuffisant."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     * @response 503 {"message":"Le versement n'a pas pu être effectué. Votre solde n'a pas été débité. Veuillez réessayer."}
     */
    public function store(DemanderRetraitRequest $request): JsonResponse
    {
        $retrait = $this->service->demander(
            $this->prestataire($request),
            (int) $request->validated('montant'),
            $request->validated('telephone_versement'),
            OperateurRetrait::from($request->validated('operateur')),
        );

        return (new RetraitResource($retrait))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    private function prestataire(Request $request): Prestataire
    {
        return $request->user()->prestataire ?? throw new AccessDeniedHttpException;
    }
}
