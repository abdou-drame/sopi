<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\DemandePrestataireService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Administration — demandes d'inscription
 */
class JustificatifController extends Controller
{
    public function __construct(private readonly DemandePrestataireService $service) {}

    /**
     * Télécharger un justificatif
     *
     * Renvoie le fichier en flux depuis le stockage privé (affichage direct pour les PDF et images).
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant du justificatif. Example: 0198a1b2-0000-7000-8000-000000000010
     *
     * @response 200 scenario="Fichier" Contenu binaire du fichier (Content-Type: application/pdf, image/jpeg ou image/png)
     * @response 404 {"message":"Justificatif introuvable."}
     */
    public function telecharger(string $id): JsonResponse|Response
    {
        $justificatif = $this->service->trouverJustificatif($id);

        if ($justificatif === null) {
            return response()->json(['message' => 'Justificatif introuvable.'], Response::HTTP_NOT_FOUND);
        }
        if (! $this->service->fichierExiste($justificatif)) {
            return response()->json(['message' => 'Fichier introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->service->telecharger($justificatif);
    }
}
