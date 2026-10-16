<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ReinitialiserMotDePasseRequest;
use App\Services\ReinitialisationMotDePasseService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * Page web de réinitialisation du mot de passe (lien reçu par e-mail). Même logique que l'API JSON : tout passe par
 * ReinitialisationMotDePasseService. Le jeton n'est ni journalisé ni renvoyé dans la page ; le formulaire est protégé par CSRF.
 */
class ReinitialisationMotDePassePageController extends Controller
{
    public function formulaire(string $jeton, ReinitialisationMotDePasseService $service): Response
    {
        if ($service->trouverValide($jeton) === null) {
            return $this->lienInvalide();
        }

        return response()->view('reinitialisation.formulaire', ['erreurs' => []]);
    }

    public function enregistrer(string $jeton, Request $request, ReinitialisationMotDePasseService $service): Response
    {
        $validateur = Validator::make(
            ['jeton' => $jeton] + $request->only(['mot_de_passe', 'mot_de_passe_confirmation']),
            ReinitialiserMotDePasseRequest::regles(),
            ReinitialiserMotDePasseRequest::messagesFr(),
        );
        if ($validateur->fails()) {
            // Page réaffichée directement (sans redirection ni retour des champs saisis : un mot de passe n'est jamais renvoyé).
            return response()->view('reinitialisation.formulaire', ['erreurs' => $validateur->errors()->all()], 422);
        }

        if (! $service->reinitialiser($jeton, $validateur->validated()['mot_de_passe'])) {
            return $this->lienInvalide();
        }

        return response()->view('reinitialisation.succes');
    }

    private function lienInvalide(): Response
    {
        return response()->view('reinitialisation.erreur', [], 404);
    }
}
