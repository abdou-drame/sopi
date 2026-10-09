<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConnexionRequest;
use App\Http\Requests\Auth\InscriptionRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
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

    public function connexion(ConnexionRequest $request): JsonResponse
    {
        $user = User::where('telephone', $request->validated('telephone'))->first();

        // Le contrôle du mot de passe est fait même quand le compte n'existe pas, pour garder
        // un temps de réponse comparable et ne pas révéler quels numéros sont inscrits.
        $motDePasseValide = Hash::check(
            $request->validated('password'),
            $user?->password ?? $this->empreinteFactice(),
        );
        if (! $user || ! $motDePasseValide) {
            return response()->json(['message' => 'Téléphone ou mot de passe incorrect.'], 401);
        }
        // Seul un compte actif s'authentifie ; le statut n'est révélé qu'avec le bon mot de passe.
        if (! $user->estActif()) {
            return response()->json(['message' => $user->statut->messageRefus()], 403);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
        ]);
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
