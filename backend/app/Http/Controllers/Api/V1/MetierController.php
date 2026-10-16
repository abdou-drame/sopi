<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MetierPublicResource;
use App\Models\Metier;

/**
 * @group Référentiels publics
 *
 * Listes utilisées par l'application avant la connexion (accueil, filtres, inscription d'un prestataire).
 */
class MetierController extends Controller
{
    /**
     * Lister les métiers
     *
     * Métiers actifs uniquement, triés par nom. Sans authentification. Le `code` est la valeur à passer aux filtres
     * (`GET /prestataires?metier=`) et à l'inscription d'un prestataire. Aucune icône n'est stockée : l'application en associe une au code.
     *
     * @unauthenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000010","code":"coiffure","nom":"Coiffure"},{"id":"0198a1b2-0000-7000-8000-000000000011","code":"mecanique","nom":"Mécanique"}]}
     */
    public function index()
    {
        return MetierPublicResource::collection(Metier::query()->actifs()->orderByRaw('lower(libelle)')->orderBy('id')->get());
    }
}
