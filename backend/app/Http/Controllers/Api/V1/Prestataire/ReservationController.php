<?php

namespace App\Http\Controllers\Api\V1\Prestataire;

use App\Exceptions\ReservationImpossibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prestataire\AnnulerReservationRequest;
use App\Http\Requests\Prestataire\DemarrerReservationRequest;
use App\Http\Requests\Prestataire\ListerReservationsRequest;
use App\Http\Requests\Prestataire\RefuserReservationRequest;
use App\Http\Resources\DemandeReservationResource;
use App\Models\Prestataire;
use App\Services\AnnulationPrestataireService;
use App\Services\DeroulementReservationService;
use App\Services\ReponseReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Prestataire — demandes de réservation
 *
 * Routes réservées aux prestataires (jeton Sanctum + rôle `prestataire`). Un prestataire ne voit que les réservations
 * de ses propres créneaux ; celles d'un autre répondent 404, comme une réservation inexistante. Le client n'est identifié
 * que par son prénom et l'initiale de son nom.
 */
class ReservationController extends Controller
{
    public function __construct(private readonly ReponseReservationService $service) {}

    /**
     * Mes demandes de réservation
     *
     * Paginée (20 par page), de la plus récente à la plus ancienne. Filtre optionnel `statut`.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"en_attente","dateCreation":"2030-01-15T08:50:00+00:00","motifRefus":null,"creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"client":{"prenom":"Awa","initialeNom":"D"}}],"links":{"first":"https://api.exemple.test/api/v1/prestataire/reservations?page=1","last":"https://api.exemple.test/api/v1/prestataire/reservations?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le statut doit être l'un de : en_attente, acceptee, confirmee, en_cours, terminee, refusee, annulee.","errors":{"statut":["Le statut doit être l'un de : en_attente, acceptee, confirmee, en_cours, terminee, refusee, annulee."]}}
     */
    public function index(ListerReservationsRequest $request)
    {
        return DemandeReservationResource::collection($this->service->lister($this->prestataire($request), $request->statut()));
    }

    /**
     * Détail d'une demande
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000060","statut":"en_attente","dateCreation":"2030-01-15T08:50:00+00:00","motifRefus":null,"creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"client":{"prenom":"Awa","initialeNom":"D"}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Réservation introuvable."}
     */
    public function show(Request $request, string $id): JsonResponse|DemandeReservationResource
    {
        $reservation = $this->service->trouver($this->prestataire($request), $id);

        return $reservation === null
            ? response()->json(['message' => 'Réservation introuvable.'], Response::HTTP_NOT_FOUND)
            : new DemandeReservationResource($reservation);
    }

    /**
     * Accepter une demande
     *
     * Possible seulement pour une demande `en_attente` reçue il y a moins de 12 h. Elle passe à `acceptee` (le client a
     * alors 2 h pour payer) et le créneau reste réservé.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000060","statut":"acceptee","dateCreation":"2030-01-15T08:50:00+00:00","motifRefus":null,"creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"client":{"prenom":"Awa","initialeNom":"D"}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Réservation introuvable."}
     * @response 409 {"message":"Cette demande a déjà reçu une réponse."}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function accepter(Request $request, string $id): JsonResponse|DemandeReservationResource
    {
        try {
            return new DemandeReservationResource($this->service->accepter($this->prestataire($request), $id));
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }
    }

    /**
     * Refuser une demande
     *
     * Mêmes conditions que l'acceptation. La demande passe à `refusee` avec le motif et le créneau est libéré.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000060","statut":"refusee","dateCreation":"2030-01-15T08:50:00+00:00","motifRefus":"Je ne suis pas disponible à cette heure.","creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"client":{"prenom":"Awa","initialeNom":"D"}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Réservation introuvable."}
     * @response 409 {"message":"Le délai de réponse est dépassé."}
     * @response 422 {"message":"Le motif du refus est obligatoire.","errors":{"motif":["Le motif du refus est obligatoire."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function refuser(RefuserReservationRequest $request, string $id): JsonResponse|DemandeReservationResource
    {
        try {
            return new DemandeReservationResource($this->service->refuser($this->prestataire($request), $id, $request->validated('motif')));
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }
    }

    /**
     * Démarrer la prestation avec le code du client
     *
     * Le client communique son code de réservation (6 caractères alphanumériques) au prestataire, qui le saisit ici :
     * la réservation `confirmee` passe à `en_cours`. Minuscules et espaces autour du code sont acceptés. Limité à
     * 5 essais par minute pour une même réservation. Le code n'est jamais renvoyé.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000060","statut":"en_cours","dateCreation":"2030-01-15T08:50:00+00:00","motifRefus":null,"creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"client":{"prenom":"Awa","initialeNom":"D"}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Réservation introuvable."}
     * @response 409 {"message":"Cette réservation ne peut pas être démarrée."}
     * @response 422 {"message":"Code de réservation incorrect.","errors":{"code":["Code de réservation incorrect."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function demarrer(DemarrerReservationRequest $request, DeroulementReservationService $deroulement, string $id): JsonResponse|DemandeReservationResource
    {
        try {
            return new DemandeReservationResource($deroulement->demarrer($this->prestataire($request), $id, $request->validated('code')));
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }
    }

    /**
     * Terminer la prestation
     *
     * La réservation `en_cours` passe à `terminee`. Le créneau reste réservé. Aucun corps.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000060","statut":"terminee","dateCreation":"2030-01-15T08:50:00+00:00","motifRefus":null,"creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"client":{"prenom":"Awa","initialeNom":"D"}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Réservation introuvable."}
     * @response 409 {"message":"Cette réservation ne peut pas être terminée."}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function terminer(Request $request, DeroulementReservationService $deroulement, string $id): JsonResponse|DemandeReservationResource
    {
        try {
            return new DemandeReservationResource($deroulement->terminer($this->prestataire($request), $id));
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }
    }

    /**
     * Annuler une réservation acceptée ou confirmée
     *
     * Réservé aux réservations `acceptee` ou `confirmee` dont le créneau n'a pas commencé. Le motif (3 à 500 caractères) est
     * transmis au client (champ `motifRefus`), le créneau est libéré et, si le client avait payé l'acompte, il lui est
     * intégralement remboursé. Un incident chez le prestataire de paiement ne fait pas échouer la route : le remboursement
     * est repris automatiquement. Pour une demande `en_attente`, utiliser le refus.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de la réservation. Example: 0198a1b2-0000-7000-8000-000000000060
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000060","statut":"annulee","dateCreation":"2030-01-15T08:50:00+00:00","motifRefus":"Un imprévu m'empêche d'assurer la prestation.","creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"client":{"prenom":"Awa","initialeNom":"D"}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Réservation introuvable."}
     * @response 409 {"message":"Le créneau a déjà commencé."}
     * @response 422 {"message":"Le motif de l'annulation est obligatoire.","errors":{"motif":["Le motif de l'annulation est obligatoire."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function annuler(AnnulerReservationRequest $request, AnnulationPrestataireService $annulation, string $id): JsonResponse|DemandeReservationResource
    {
        try {
            return new DemandeReservationResource($annulation->annuler($this->prestataire($request), $id, $request->validated('motif')));
        } catch (ReservationImpossibleException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statut);
        }
    }

    private function prestataire(Request $request): Prestataire
    {
        return $request->user()->prestataire ?? throw new AccessDeniedHttpException;
    }
}
