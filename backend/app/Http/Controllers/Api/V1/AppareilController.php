<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlateformeAppareil;
use App\Enums\StatutCompte;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appareil\EnregistrerAppareilRequest;
use App\Services\AppareilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Appareils
 *
 * Appareils recevant les notifications push (jeton FCM). Client et prestataire uniquement. Le jeton n'est jamais renvoyé.
 *
 * @authenticated
 */
class AppareilController extends Controller
{
    /**
     * Enregistrer mon appareil
     *
     * Enregistre ou met à jour le jeton FCM de l'appareil. Un jeton n'appartient qu'à un compte : s'il était à un autre
     * utilisateur, il lui est retiré (le dernier connecté l'emporte). Réservé aux comptes actifs.
     *
     * @bodyParam jeton string required Jeton FCM de l'appareil. Example: fcm-jeton-exemple-0123456789
     * @bodyParam plateforme string required android ou ios. Example: android
     *
     * @response 200 {"message":"Appareil enregistré."}
     * @response 403 {"message":"Votre compte n'est pas actif : les notifications ne peuvent pas être activées.","statutCompte":"suspendu"}
     * @response 422 {"message":"La plateforme doit être android ou ios.","errors":{"plateforme":["La plateforme doit être android ou ios."]}}
     */
    public function enregistrer(EnregistrerAppareilRequest $request, AppareilService $appareils): JsonResponse
    {
        $utilisateur = $request->user();
        if ($utilisateur->statut !== StatutCompte::Actif) {
            return response()->json(['message' => 'Votre compte n\'est pas actif : les notifications ne peuvent pas être activées.', 'statutCompte' => $utilisateur->statut->value], 403);
        }

        $appareils->enregistrer($utilisateur, $request->validated('jeton'), PlateformeAppareil::from($request->validated('plateforme')));

        return response()->json(['message' => 'Appareil enregistré.']);
    }

    /**
     * Retirer mon appareil
     *
     * À appeler à la déconnexion. Sans effet (200) si le jeton n'est pas à vous ou n'existe pas.
     *
     * @urlParam jeton string required Jeton FCM de l'appareil. Example: fcm-jeton-exemple-0123456789
     *
     * @response 200 {"message":"Appareil retiré."}
     */
    public function retirer(Request $request, AppareilService $appareils, string $jeton): JsonResponse
    {
        $appareils->retirer($request->user(), $jeton);

        return response()->json(['message' => 'Appareil retiré.']);
    }
}
