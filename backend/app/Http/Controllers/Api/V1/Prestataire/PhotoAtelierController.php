<?php

namespace App\Http\Controllers\Api\V1\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prestataire\AjouterPhotoRequest;
use App\Models\Prestataire;
use App\Services\PhotoAtelierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as ReponseVide;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Prestataire — photos d'atelier
 *
 * Routes réservées aux prestataires (jeton Sanctum + rôle `prestataire`). 6 photos au plus, stockées sur un disque privé.
 * Les clients voient ces photos sur le profil public du prestataire.
 */
class PhotoAtelierController extends Controller
{
    public function __construct(private readonly PhotoAtelierService $service) {}

    /**
     * Mes photos
     *
     * Photos du prestataire connecté, dans l'ordre d'ajout. `url` est un chemin de l'API à compléter avec l'URL de base ; l'image se charge avec le jeton.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000030","url":"/api/v1/prestataire/photos/0198a1b2-0000-7000-8000-000000000030"}]}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     */
    public function index(Request $request): JsonResponse
    {
        $ids = $this->service->lister($this->prestataire($request));

        return response()->json(['data' => array_map(fn (string $id) => ['id' => $id, 'url' => route('prestataire.photo', ['photo' => $id], false)], $ids)]);
    }

    /**
     * Afficher une de mes photos
     *
     * Renvoie l'image en flux (Content-Type réel, `nosniff`, `Cache-Control: private`). La photo d'un autre prestataire répond 404, comme une photo inexistante.
     *
     * @authenticated
     *
     * @urlParam photo string required Identifiant de la photo. Example: 0198a1b2-0000-7000-8000-000000000030
     *
     * @response 200 scenario="image" [Contenu binaire de l'image]
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Photo introuvable."}
     */
    public function afficher(Request $request, string $photo): JsonResponse|StreamedResponse
    {
        return $this->service->flux($this->prestataire($request), $photo)
            ?? response()->json(['message' => 'Photo introuvable.'], Response::HTTP_NOT_FOUND);
    }

    /**
     * Ajouter une photo
     *
     * Envoi multipart, champ `photo`. Le type réel est vérifié sur le contenu. Au-delà de 6 photos, l'envoi est refusé.
     *
     * @authenticated
     *
     * @response 201 {"id":"0198a1b2-0000-7000-8000-000000000030"}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Vous ne pouvez pas ajouter plus de 6 photos.","errors":{"photo":["Vous ne pouvez pas ajouter plus de 6 photos."]}}
     */
    public function store(AjouterPhotoRequest $request): JsonResponse
    {
        $id = $this->service->ajouter($this->prestataire($request), $request->file('photo'));

        if ($id === null) {
            $message = 'Vous ne pouvez pas ajouter plus de '.PhotoAtelierService::NB_MAX.' photos.';

            return response()->json(['message' => $message, 'errors' => ['photo' => [$message]]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['id' => $id], Response::HTTP_CREATED);
    }

    /**
     * Supprimer une photo
     *
     * Supprime l'entrée et le fichier. La photo d'un autre prestataire répond 404, comme une photo inexistante.
     *
     * @authenticated
     *
     * @urlParam photo string required Identifiant de la photo. Example: 0198a1b2-0000-7000-8000-000000000030
     *
     * @response 204
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 404 {"message":"Photo introuvable."}
     */
    public function destroy(Request $request, string $photo): JsonResponse|ReponseVide
    {
        if (! $this->service->supprimer($this->prestataire($request), $photo)) {
            return response()->json(['message' => 'Photo introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return response()->noContent();
    }

    private function prestataire(Request $request): Prestataire
    {
        return $request->user()->prestataire ?? throw new AccessDeniedHttpException;
    }
}
