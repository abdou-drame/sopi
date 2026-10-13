<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profil\ChangerMotDePasseRequest;
use App\Http\Requests\Profil\EnregistrerJetonPushRequest;
use App\Http\Requests\Profil\ModifierProfilRequest;
use App\Http\Resources\ProfilResource;
use App\Services\JetonPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @group Profil
 *
 * Profil de l'utilisateur connecté (client, prestataire ou administrateur).
 *
 * @authenticated
 */
class ProfilController extends Controller
{
    /**
     * Voir mon profil
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000001","nom":"Diop","prenom":"Awa","email":"awa@example.com","telephone":"+221771234567","role":"client","statutCompte":"actif","dateCreation":"2026-10-11T09:30:00+00:00"}
     * @response 401 {"message":"Authentification requise."}
     */
    public function afficher(Request $request): ProfilResource
    {
        return new ProfilResource($request->user());
    }

    /**
     * Modifier mon profil
     *
     * Modifie le nom, le prénom et le téléphone. L'e-mail et le rôle ne sont pas modifiables (ignorés s'ils sont envoyés).
     *
     * @bodyParam nom string required Nom. Example: Diop
     * @bodyParam prenom string required Prénom. Example: Awa
     * @bodyParam telephone string required Mobile sénégalais, avec ou sans +221. Example: 77 123 45 67
     *
     * @response 200 {"id":"0198a1b2-0000-7000-8000-000000000001","nom":"Diop","prenom":"Awa","email":"awa@example.com","telephone":"+221771234567","role":"client","statutCompte":"actif","dateCreation":"2026-10-11T09:30:00+00:00"}
     * @response 401 {"message":"Authentification requise."}
     * @response 422 {"message":"Ce numéro de téléphone est déjà utilisé.","errors":{"telephone":["Ce numéro de téléphone est déjà utilisé."]}}
     */
    public function modifier(ModifierProfilRequest $request): ProfilResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return new ProfilResource($user);
    }

    /**
     * Changer mon mot de passe
     *
     * Vérifie le mot de passe actuel, puis révoque tous les autres jetons de l'utilisateur. Le jeton courant reste valide.
     *
     * @bodyParam mot_de_passe_actuel string required Mot de passe actuel. Example: ancienmotdepasse
     * @bodyParam nouveau_mot_de_passe string required 8 caractères minimum. Example: nouveaumotdepasse
     * @bodyParam confirmation string required Identique au nouveau mot de passe. Example: nouveaumotdepasse
     *
     * @response 200 {"message":"Votre mot de passe a été modifié."}
     * @response 401 {"message":"Authentification requise."}
     * @response 422 {"message":"Le mot de passe actuel est incorrect.","errors":{"mot_de_passe_actuel":["Le mot de passe actuel est incorrect."]}}
     */
    public function changerMotDePasse(ChangerMotDePasseRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('nouveau_mot_de_passe')]);

        // Révoque les autres appareils ; le jeton utilisé pour cette requête est conservé.
        $courant = $user->currentAccessToken();
        $user->tokens()
            ->when($courant instanceof PersonalAccessToken, fn ($q) => $q->where('id', '!=', $courant->id))
            ->delete();

        return response()->json(['message' => 'Votre mot de passe a été modifié.']);
    }

    /**
     * Enregistrer mon jeton push
     *
     * Enregistre le jeton FCM de l'appareil pour les notifications push. Un jeton désigne un appareil : s'il était déjà
     * enregistré par un autre compte, il lui est retiré (la dernière affectation l'emporte). Le jeton n'est jamais renvoyé par l'API.
     *
     * @bodyParam jetonPush string required Jeton FCM. Example: fcm-jeton-exemple
     *
     * @response 200 {"message":"Jeton de notification enregistré."}
     * @response 401 {"message":"Authentification requise."}
     * @response 422 {"message":"Le champ jeton push est obligatoire.","errors":{"jetonPush":["Le champ jeton push est obligatoire."]}}
     */
    public function enregistrerJetonPush(EnregistrerJetonPushRequest $request, JetonPushService $jetons): JsonResponse
    {
        $jetons->enregistrer($request->user(), $request->validated('jetonPush'));

        return response()->json(['message' => 'Jeton de notification enregistré.']);
    }

    /**
     * Retirer mon jeton push
     *
     * À appeler quand l'appareil se déconnecte ou que les notifications push sont désactivées. Sans effet si aucun jeton n'est enregistré.
     *
     * @response 200 {"message":"Jeton de notification retiré."}
     * @response 401 {"message":"Authentification requise."}
     */
    public function retirerJetonPush(Request $request, JetonPushService $jetons): JsonResponse
    {
        $jetons->retirer($request->user());

        return response()->json(['message' => 'Jeton de notification retiré.']);
    }
}
