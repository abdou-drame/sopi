<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListeUtilisateursRequest;
use App\Http\Resources\UtilisateurAdminResource;
use App\Services\UtilisateursAdminService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Administration — utilisateurs
 *
 * Routes de lecture réservées aux administrateurs (jeton Sanctum + rôle `administrateur`). Les comptes administrateurs ne sont
 * jamais listés ni ouverts (404). Nom, e-mail et téléphone sont visibles (supervision) ; jamais de mot de passe ni de jeton.
 */
class UtilisateurController extends Controller
{
    public function __construct(private readonly UtilisateursAdminService $service) {}

    /**
     * Liste des utilisateurs
     *
     * Clients et prestataires, tous statuts confondus, du plus récent au plus ancien (20 par page). Filtres combinables : `role`,
     * `statut`, `q` (recherche partielle, insensible à la casse, dans nom, prénom, « prénom nom », e-mail, téléphone et nom d'entreprise ;
     * les accents ne sont pas ignorés), `debut` / `fin` (date de création). `prestataire` vaut `null` pour un client ; son `id` est
     * l'identifiant du profil prestataire, celui des autres routes d'administration.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"id":"0198a1b2-0000-7000-8000-000000000002","role":"prestataire","prenom":"Moussa","nom":"Diop","email":"moussa@example.com","telephone":"+221770000002","statut":"actif","dateCreation":"2030-01-10T09:00:00+00:00","prestataire":{"id":"0198a1b2-0000-7000-8000-000000000012","metier":"mecanique","quartier":"Malika","nomEntreprise":"Garage Exemple"}},{"id":"0198a1b2-0000-7000-8000-000000000001","role":"client","prenom":"Awa","nom":"Diallo","email":null,"telephone":"+221770000001","statut":"actif","dateCreation":"2030-01-09T09:00:00+00:00","prestataire":null}],"links":{"first":"https://api.exemple.test/api/v1/admin/utilisateurs?page=1","last":"https://api.exemple.test/api/v1/admin/utilisateurs?page=1","prev":null,"next":null},"meta":{"current_page":1,"last_page":1,"per_page":20,"total":2}}
     * @response 401 {"message":"Authentification requise."}
     * @response 403 {"message":"Vous n'avez pas le droit d'effectuer cette action."}
     * @response 422 {"message":"Le rôle doit être client ou prestataire.","errors":{"role":["Le rôle doit être client ou prestataire."]}}
     */
    public function index(ListeUtilisateursRequest $requete)
    {
        return UtilisateurAdminResource::collection($this->service->lister($requete->filtres()));
    }

    /**
     * Fiche d'un utilisateur
     *
     * Identité, statut, `sanctionActive` (blocage en cours, sinon avertissement non levé, sinon `null`) et `nombreSanctions`.
     * Un client a un bloc `client` (`reservationsParStatut`, `nombreAvisDonnes`, `litiges`), un prestataire un bloc `prestataire`
     * (adresse, `solde` en FCFA, `prestationsActives`, `reservationsParStatut`, `noteMoyenne` et `nombreAvis` hors avis masqués,
     * `nombreRetraits`, `litiges`) ; l'autre bloc vaut `null`. `litiges` : `total` et `ouverts` (concernant le compte).
     *
     * @authenticated
     *
     * @urlParam id string required Identifiant de l'utilisateur. Example: 0198a1b2-0000-7000-8000-000000000002
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000002","role":"prestataire","prenom":"Moussa","nom":"Diop","email":"moussa@example.com","telephone":"+221770000002","statut":"suspendu","dateCreation":"2030-01-10T09:00:00+00:00","prestataire":{"id":"0198a1b2-0000-7000-8000-000000000012","metier":"mecanique","quartier":"Malika","nomEntreprise":"Garage Exemple","adresse":"Rue 10, près du marché","solde":12000,"prestationsActives":3,"reservationsParStatut":{"en_attente":1,"acceptee":0,"confirmee":2,"en_cours":0,"terminee":14,"refusee":1,"annulee":2},"noteMoyenne":4.5,"nombreAvis":10,"nombreRetraits":2,"litiges":{"total":1,"ouverts":0}},"sanctionActive":{"id":"0198a1b2-0000-7000-8000-000000000040","type":"suspension_temporaire","dateFin":"2030-02-01T09:00:00+00:00"},"nombreSanctions":2,"client":null}
     * @response 404 {"message":"Utilisateur introuvable."}
     */
    public function show(string $id): JsonResponse|UtilisateurAdminResource
    {
        $utilisateur = $this->service->trouver($id);

        return $utilisateur === null
            ? response()->json(['message' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND)
            : UtilisateurAdminResource::fiche($utilisateur, $this->service->synthese($utilisateur));
    }
}
