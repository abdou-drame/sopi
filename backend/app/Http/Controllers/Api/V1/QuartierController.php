<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatutCompte;
use App\Http\Controllers\Controller;
use App\Models\Prestataire;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * @group Référentiels publics
 */
class QuartierController extends Controller
{
    /**
     * Lister les quartiers
     *
     * Quartiers où au moins un prestataire au compte actif est installé, sans doublon (casse et espaces superflus ignorés), triés
     * par ordre alphabétique. Sans authentification. Il n'existe pas de table des quartiers : la liste vient des profils.
     * Passez la valeur telle quelle au filtre `GET /prestataires?quartier=`.
     *
     * @unauthenticated
     *
     * @response 200 {"data":["Jaxaay","Keur Massar Nord","Malika"]}
     */
    public function index(): JsonResponse
    {
        $quartiers = Prestataire::query()
            ->whereHas('user', fn ($q) => $q->where('statut', StatutCompte::Actif->value))
            ->orderBy('quartier')
            ->pluck('quartier')
            ->map(fn (string $q) => trim($q))
            ->filter(fn (string $q) => $q !== '')
            ->unique(fn (string $q) => mb_strtolower($q))
            ->sortBy(fn (string $q) => Str::lower(Str::ascii($q)))
            ->values();

        return response()->json(['data' => $quartiers]);
    }
}
