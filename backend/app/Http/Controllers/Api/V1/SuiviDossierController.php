<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuiviDossierResource;
use App\Services\SuiviDossierService;
use Illuminate\Http\JsonResponse;

/**
 * @group Prestataires
 */
class SuiviDossierController extends Controller
{
    /**
     * Suivi du dossier d'inscription
     *
     * Route publique : le prestataire non encore actif suit son dossier avec le lien personnel reçu à l'inscription, puis dans l'e-mail du résultat de l'examen.
     * Ne renvoie aucune donnée personnelle. Le motif de refus n'est renseigné que si le dossier est refusé.
     * Un jeton inconnu renvoie toujours la même réponse 404. Limité à 30 requêtes par minute et par adresse IP.
     *
     * @unauthenticated
     *
     * @urlParam jeton string required Jeton de suivi reçu à l'inscription ou dans l'e-mail du résultat de l'examen. Example: jeton-de-suivi-exemple-0123456789-abcdefghijklmnop
     *
     * @response 200 {"nomEntreprise":"Garage Moussa","metier":"mecanique","statutCompte":"en_attente","motifRefus":null,"dateSoumission":"2026-10-12T09:30:00+00:00","justificatifs":[{"typeDocument":"cni","statut":"en_attente"},{"typeDocument":"diplome","statut":"en_attente"}]}
     * @response 404 {"message":"Lien de suivi invalide ou expiré."}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function __invoke(string $jeton, SuiviDossierService $service): JsonResponse
    {
        $prestataire = $service->trouverParJeton($jeton);

        if ($prestataire === null) {
            return response()->json(['message' => 'Lien de suivi invalide ou expiré.'], 404);
        }

        return (new SuiviDossierResource($prestataire))->response();
    }
}
