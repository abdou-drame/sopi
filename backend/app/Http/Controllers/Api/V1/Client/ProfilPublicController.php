<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfilPublicPrestataireResource;
use App\Services\ProfilPublicService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Client — profil public d'un prestataire
 *
 * Routes réservées aux clients (jeton Sanctum + rôle `client`). Seuls les prestataires dont le compte est actif sont visibles.
 */
class ProfilPublicController extends Controller
{
    public function __construct(private readonly ProfilPublicService $service) {}

    /**
     * Profil d'un prestataire
     *
     * Informations publiques, prestations actives et photos d'atelier. Aucune donnée personnelle et pas de note
     * (les avis n'existent pas encore). `url` est un chemin de l'API à compléter avec l'URL de base ; l'image se charge avec le jeton.
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du prestataire. Example: 0198a1b2-0000-7000-8000-000000000002
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000002","nomEntreprise":"Garage Moussa","metier":"mecanique","quartier":"Malika","adresse":"Rue 10, près du marché","prestations":[{"id":"0198a1b2-0000-7000-8000-000000000020","nom":"Vidange complète","domaine":"mecanique","dureeMinutes":60,"prix":15000}],"photos":[{"id":"0198a1b2-0000-7000-8000-000000000030","url":"/api/v1/prestataires/0198a1b2-0000-7000-8000-000000000002/photos/0198a1b2-0000-7000-8000-000000000030"}]}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Prestataire introuvable."}
     */
    public function show(string $id): JsonResponse|ProfilPublicPrestataireResource
    {
        $prestataire = $this->service->trouver($id);

        if ($prestataire === null) {
            return response()->json(['message' => 'Prestataire introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return new ProfilPublicPrestataireResource($prestataire);
    }

    /**
     * Photo d'atelier
     *
     * Renvoie l'image en flux (Content-Type réel, `nosniff`, `Cache-Control: private`).
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du prestataire. Example: 0198a1b2-0000-7000-8000-000000000002
     * @urlParam photo string required Identifiant de la photo. Example: 0198a1b2-0000-7000-8000-000000000030
     *
     * @response 200 scenario="image" [Contenu binaire de l'image]
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Photo introuvable."}
     */
    public function photo(string $id, string $photo): JsonResponse|StreamedResponse
    {
        return $this->service->photo($id, $photo)
            ?? response()->json(['message' => 'Photo introuvable.'], Response::HTTP_NOT_FOUND);
    }
}
