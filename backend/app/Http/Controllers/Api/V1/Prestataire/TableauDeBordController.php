<?php

namespace App\Http\Controllers\Api\V1\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Resources\DemandeReservationResource;
use App\Models\Prestataire;
use App\Services\TableauPrestataireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Prestataire — tableau de bord
 *
 * Route réservée aux prestataires (jeton Sanctum + rôle `prestataire`). Le jour et le mois sont ceux du fuseau Africa/Dakar.
 */
class TableauDeBordController extends Controller
{
    public function __construct(private readonly TableauPrestataireService $service) {}

    /**
     * Mon tableau de bord
     *
     * Nombre de rendez-vous du jour (réservations `confirmee` ou `en_cours` dont le créneau commence aujourd'hui), nombre de
     * prestations terminées dans le mois, et planning du jour par heure croissante. Le client n'est identifié que par son
     * prénom et l'initiale de son nom.
     *
     * @authenticated
     *
     * @response 200 {"rendezVousAujourdhui":1,"terminesCeMois":4,"planningDuJour":[{"id":"0198a1b2-0000-7000-8000-000000000060","statut":"confirmee","dateCreation":"2030-01-15T08:50:00+00:00","motifRefus":null,"reponseAvant":null,"creneau":{"id":"0198a1b2-0000-7000-8000-000000000040","dateDebut":"2030-01-16T09:00:00+00:00","dateFin":"2030-01-16T10:00:00+00:00"},"prestation":{"id":"0198a1b2-0000-7000-8000-000000000050","nom":"Vidange","dureeMinutes":60,"prix":15000},"client":{"prenom":"Awa","initialeNom":"D"}}]}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $prestataire = $request->user()->prestataire ?? throw new AccessDeniedHttpException;
        assert($prestataire instanceof Prestataire);
        $chiffres = $this->service->chiffres($prestataire);

        return response()->json([
            'rendezVousAujourdhui' => $chiffres['rendezVousAujourdhui'],
            'terminesCeMois' => $chiffres['terminesCeMois'],
            'planningDuJour' => DemandeReservationResource::collection($chiffres['planningDuJour'])->resolve(),
        ]);
    }
}
