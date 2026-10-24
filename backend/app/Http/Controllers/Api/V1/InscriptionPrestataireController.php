<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prestataire\InscriptionPrestataireRequest;
use App\Http\Resources\InscriptionPrestataireResource;
use App\Services\InscriptionPrestataireService;
use Illuminate\Http\JsonResponse;

/**
 * @group Prestataires
 */
class InscriptionPrestataireController extends Controller
{
    /**
     * Inscription d'un prestataire
     *
     * Envoi en `multipart/form-data`. Crée le compte (statut `en_attente`, il ne peut pas se connecter avant validation
     * par un administrateur), le profil et les justificatifs. Le dossier doit contenir au moins une CNI et un diplôme
     * ou une attestation (5 fichiers au maximum, PDF/JPG/PNG, 5 Mo chacun). Limité à 10 requêtes par heure et par adresse IP.
     *
     * Le `jeton_suivi` n'est renvoyé qu'une seule fois : seul son hachage est conservé. Les chemins des fichiers ne sont jamais renvoyés.
     *
     * @unauthenticated
     *
     * @response 201 {"id":"0198a1b2-0000-7000-8000-000000000002","nom":"Diop","prenom":"Moussa","nomEntreprise":"Garage Moussa","metier":"mecanique","statutCompte":"en_attente","jeton_suivi":"jeton-de-suivi-exemple-0123456789-abcdefghijklmnop","lien_suivi":"https://api.exemple.test/suivi/jeton-de-suivi-exemple-0123456789-abcdefghijklmnop","justificatifs":[{"type":"cni","statut":"en_attente"},{"type":"diplome","statut":"en_attente"}]}
     * @response 422 {"message":"Veuillez joindre un fichier PDF ou image valide","errors":{"justificatifs":["Veuillez joindre un fichier PDF ou image valide"]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function __invoke(InscriptionPrestataireRequest $request, InscriptionPrestataireService $service): JsonResponse
    {
        $resultat = $service->inscrire($request->validated());

        return (new InscriptionPrestataireResource($resultat['prestataire'], $resultat['jeton_suivi']))
            ->response()
            ->setStatusCode(201);
    }
}
