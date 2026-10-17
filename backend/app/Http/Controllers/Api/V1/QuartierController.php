<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatutCompte;
use App\Http\Controllers\Controller;
use App\Models\Prestataire;
use App\Models\Quartier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @group Référentiels publics
 */
class QuartierController extends Controller
{
    /**
     * Lister les quartiers
     *
     * Sans paramètre : quartiers où au moins un prestataire au compte actif est installé, sans doublon (casse et espaces
     * superflus ignorés), triés par ordre alphabétique. C'est la liste de la recherche des clients.
     *
     * Avec `tous=1` : liste complète pour l'inscription d'un prestataire, c'est-à-dire le référentiel des quartiers (table
     * `quartiers`) réuni aux quartiers des prestataires actifs, sans doublon (casse, accents et espaces en trop ignorés), triée
     * sans tenir compte des accents. Le référentiel n'est qu'une liste de suggestions : le quartier d'un prestataire reste un texte libre.
     *
     * Sans authentification. Passez la valeur telle quelle au filtre `GET /prestataires?quartier=`.
     *
     * @unauthenticated
     *
     * @queryParam tous boolean Mettre `1` (ou `true`) pour obtenir le référentiel complet. Toute autre valeur est ignorée. Example: 1
     *
     * @response 200 {"data":["Jaxaay","Keur Massar Nord","Malika"]}
     */
    public function index(Request $request): JsonResponse
    {
        $quartiers = $this->quartiersDesPrestatairesActifs();

        if (in_array(Str::lower((string) $request->query('tous')), ['1', 'true'], true)) {
            return response()->json(['data' => $this->listeComplete($quartiers)]);
        }

        $quartiers = $quartiers
            ->unique(fn (string $q) => mb_strtolower($q))
            ->sortBy(fn (string $q) => Str::lower(Str::ascii($q)))
            ->values();

        return response()->json(['data' => $quartiers]);
    }

    /** @return Collection<int, string> Quartiers des prestataires actifs, nettoyés (espaces retirés, vides écartés). */
    private function quartiersDesPrestatairesActifs(): Collection
    {
        return Prestataire::query()
            ->whereHas('user', fn ($q) => $q->where('statut', StatutCompte::Actif->value))
            ->orderBy('quartier')
            ->pluck('quartier')
            ->map(fn (string $q) => trim($q))
            ->filter(fn (string $q) => $q !== '');
    }

    /**
     * Référentiel puis quartiers libres des prestataires actifs, dédoublonnés (le libellé du référentiel l'emporte).
     *
     * @param  Collection<int, string>  $quartiersPrestataires
     * @return Collection<int, string>
     */
    private function listeComplete(Collection $quartiersPrestataires): Collection
    {
        return Quartier::query()->pluck('nom')
            ->map(fn (string $q) => trim($q))
            ->filter(fn (string $q) => $q !== '')
            ->concat($quartiersPrestataires)
            ->unique(fn (string $q) => $this->cleDeComparaison($q))
            ->sortBy(fn (string $q) => $this->cleDeComparaison($q))
            ->values();
    }

    private function cleDeComparaison(string $quartier): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($quartier))));
    }
}
