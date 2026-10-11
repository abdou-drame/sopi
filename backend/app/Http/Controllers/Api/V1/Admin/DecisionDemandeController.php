<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\DemandeDejaTraitee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefuserDemandeRequest;
use App\Http\Resources\DemandePrestataireDetailResource;
use App\Services\DecisionDemandeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Administration — demandes d'inscription
 */
class DecisionDemandeController extends Controller
{
    public function __construct(private readonly DecisionDemandeService $service) {}

    /**
     * Accepter une demande
     *
     * Le compte passe à `actif` (le prestataire peut se connecter) et ses justificatifs à `valide`.
     * Seule une demande `en_attente` peut être décidée. Aucune notification n'est envoyée : le prestataire consulte son lien de suivi.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du prestataire. Example: 0198a1b2-0000-7000-8000-000000000002
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000002","nom":"Diop","prenom":"Moussa","telephone":"+221771234567","email":"moussa@example.com","nomEntreprise":"Garage Moussa","metier":"mecanique","quartier":"Malika","adresse":"Rue 10, près du marché","statutCompte":"actif","motifRefus":null,"dateSoumission":"2026-10-12T09:30:00+00:00","justificatifs":[{"id":"0198a1b2-0000-7000-8000-000000000010","typeDocument":"cni","statut":"valide","nomOriginal":"cni.png","typeMime":"image/png","taille":184320}]}
     * @response 404 {"message":"Demande introuvable."}
     * @response 409 {"message":"Cette demande a déjà été traitée."}
     */
    public function accepter(Request $request, string $id): JsonResponse|DemandePrestataireDetailResource
    {
        return $this->repondre(fn () => $this->service->accepter($id, $request->user()));
    }

    /**
     * Refuser une demande
     *
     * Le compte passe à `refuse`, ses justificatifs à `rejete` et le motif est enregistré (visible sur le lien de suivi du prestataire).
     * Seule une demande `en_attente` peut être décidée.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du prestataire. Example: 0198a1b2-0000-7000-8000-000000000002
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000002","nom":"Diop","prenom":"Moussa","telephone":"+221771234567","email":"moussa@example.com","nomEntreprise":"Garage Moussa","metier":"mecanique","quartier":"Malika","adresse":"Rue 10, près du marché","statutCompte":"refuse","motifRefus":"La photocopie de la CNI est illisible.","dateSoumission":"2026-10-12T09:30:00+00:00","justificatifs":[{"id":"0198a1b2-0000-7000-8000-000000000010","typeDocument":"cni","statut":"rejete","nomOriginal":"cni.png","typeMime":"image/png","taille":184320}]}
     * @response 404 {"message":"Demande introuvable."}
     * @response 409 {"message":"Cette demande a déjà été traitée."}
     * @response 422 {"message":"Le motif du refus doit contenir au moins 10 caractères.","errors":{"motif":["Le motif du refus doit contenir au moins 10 caractères."]}}
     */
    public function refuser(RefuserDemandeRequest $request, string $id): JsonResponse|DemandePrestataireDetailResource
    {
        return $this->repondre(fn () => $this->service->refuser($id, $request->validated('motif'), $request->user()));
    }

    private function repondre(callable $decision): JsonResponse|DemandePrestataireDetailResource
    {
        try {
            return new DemandePrestataireDetailResource($decision());
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Demande introuvable.'], Response::HTTP_NOT_FOUND);
        } catch (DemandeDejaTraitee $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }
}
