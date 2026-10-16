<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConnexionRequest;
use App\Http\Requests\Auth\InscriptionRequest;
use App\Http\Requests\Auth\MotDePasseOublieRequest;
use App\Http\Requests\Auth\ReinitialiserMotDePasseRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\BlocageConnexionService;
use App\Services\ReinitialisationMotDePasseService;
use App\Services\SanctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function inscription(InscriptionRequest $request): JsonResponse
    {
        // Rôle et statut sont imposés ici, jamais lus dans la requête.
        $user = new User($request->validated());
        $user->role = Role::Client;
        $user->statut = StatutCompte::Actif;
        $user->save();

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Connexion
     *
     * Authentifie un compte actif. Un compte non actif reçoit 403 avec `message` et, quand elles existent, des informations
     * structurées : `motif` (sanction active, ou motif du refus d'inscription) et `finSuspension` (ISO 8601, suspension temporaire
     * seulement). Ces champs sont absents sinon.
     *
     * @group Authentification
     *
     * @unauthenticated
     *
     * @response 200 {"token":"1|abcdef","user":{"id":"0198a1b2-0000-7000-8000-000000000001","role":"client","statut":"actif"}}
     * @response 401 {"message":"Identifiant ou mot de passe incorrect."}
     * @response 403 scenario="Compte suspendu" {"message":"Votre compte est suspendu jusqu'au 10/03/2030 à 14:30. Motif : Retards répétés.","motif":"Retards répétés.","finSuspension":"2030-03-10T14:30:00+00:00"}
     * @response 403 scenario="Inscription refusée" {"message":"Votre demande d'inscription a été refusée.","motif":"Pièces illisibles."}
     */
    public function connexion(ConnexionRequest $request, BlocageConnexionService $blocage, SanctionService $sanctions): JsonResponse
    {
        $identifiant = $request->validated('identifiant');

        // Pendant le blocage, même le bon mot de passe est refusé (et sans consulter la base).
        $secondes = $blocage->secondesRestantes($identifiant);
        if ($secondes > 0) {
            return response()->json(['message' => $blocage->message($secondes)], 429, ['Retry-After' => $secondes]);
        }

        $user = User::where(str_contains($identifiant, '@') ? 'email' : 'telephone', $identifiant)->first();

        // Le contrôle du mot de passe est fait même quand le compte n'existe pas, pour garder
        // un temps de réponse comparable et ne pas révéler quels identifiants sont inscrits.
        $motDePasseValide = Hash::check(
            $request->validated('password'),
            $user?->password ?? $this->empreinteFactice(),
        );
        if (! $user || ! $motDePasseValide) {
            // Même traitement que le compte existe ou non.
            $blocage->enregistrerEchec($identifiant);

            return response()->json(['message' => 'Identifiant ou mot de passe incorrect.'], 401);
        }
        $blocage->reinitialiser($identifiant);

        // Seul un compte actif s'authentifie ; le statut n'est révélé qu'avec le bon mot de passe.
        if (! $user->estActif()) {
            return response()->json(['message' => $sanctions->messageRefusConnexion($user)] + $sanctions->detailRefusConnexion($user), 403);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Mot de passe oublié
     *
     * Envoie un lien de réinitialisation (valable 60 minutes, usage unique) à l'adresse e-mail d'un compte actif. La réponse est
     * toujours la même, que l'adresse corresponde à un compte ou non (compte inconnu, sans e-mail, suspendu, banni, en attente,
     * refusé ou administrateur : aucun e-mail n'est envoyé). Limité par adresse IP et par adresse e-mail.
     *
     * @group Authentification
     *
     * @unauthenticated
     *
     * @response 200 {"message":"Si un compte correspond à cette adresse, un lien de réinitialisation vient d'être envoyé."}
     * @response 422 {"message":"L'adresse e-mail n'est pas valide.","errors":{"email":["L'adresse e-mail n'est pas valide."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function motDePasseOublie(MotDePasseOublieRequest $request, ReinitialisationMotDePasseService $reinitialisation): JsonResponse
    {
        $reinitialisation->demander($request->validated('email'));

        return response()->json(['message' => ReinitialisationMotDePasseService::MESSAGE_DEMANDE]);
    }

    /**
     * Réinitialiser le mot de passe
     *
     * Choisit un nouveau mot de passe avec le jeton du lien reçu par e-mail (valable 60 minutes, usage unique). Tous les appareils
     * du compte sont déconnectés et un e-mail de confirmation est envoyé.
     *
     * @group Authentification
     *
     * @unauthenticated
     *
     * @response 200 {"message":"Votre mot de passe a été modifié. Vous pouvez vous reconnecter."}
     * @response 422 {"message":"Ce lien est invalide, expiré ou déjà utilisé. Faites une nouvelle demande de réinitialisation.","errors":{"jeton":["Ce lien est invalide, expiré ou déjà utilisé. Faites une nouvelle demande de réinitialisation."]}}
     * @response 429 {"message":"Trop de tentatives. Réessayez dans quelques instants."}
     */
    public function reinitialiserMotDePasse(ReinitialiserMotDePasseRequest $request, ReinitialisationMotDePasseService $reinitialisation): JsonResponse
    {
        if (! $reinitialisation->reinitialiser($request->validated('jeton'), $request->validated('mot_de_passe'))) {
            return response()->json([
                'message' => ReinitialiserMotDePasseRequest::MESSAGE_LIEN_INVALIDE,
                'errors' => ['jeton' => [ReinitialiserMotDePasseRequest::MESSAGE_LIEN_INVALIDE]],
            ], 422);
        }

        return response()->json(['message' => 'Votre mot de passe a été modifié. Vous pouvez vous reconnecter.']);
    }

    public function deconnexion(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Vous êtes déconnecté.']);
    }

    // Empreinte factice (calculée une fois) : sert à exécuter Hash::check même si le numéro est inconnu.
    private function empreinteFactice(): string
    {
        static $empreinte = null;

        return $empreinte ??= Hash::make(Str::random(40));
    }

    public function moi(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
