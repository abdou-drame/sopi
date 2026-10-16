<?php

use App\Http\Controllers\ReinitialisationMotDePassePageController;
use App\Http\Controllers\SuiviDossierPageController;
use App\Http\Middleware\EntetesPagePrivee;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Pages où DexPay renvoie le client après le paiement : textes fixes, aucune donnée lue de l'URL.
Route::get('/paiement/succes', fn () => view('paiement.retour', [
    'titre' => 'Paiement reçu',
    'message' => 'Votre acompte a bien été reçu. Retournez dans l\'application Sopi pour voir votre réservation.',
]));

Route::get('/paiement/echec', fn () => view('paiement.retour', [
    'titre' => 'Paiement non abouti',
    'message' => 'Le paiement n\'a pas abouti. Aucun montant ne vous sera prélevé. Retournez dans l\'application Sopi pour réessayer.',
]));

// Page de suivi du dossier d'inscription (lien reçu par e-mail). Même limiteur que l'API JSON de suivi.
Route::get('/suivi/{jeton}', SuiviDossierPageController::class)->middleware('throttle:suivi-dossier');

// Réinitialisation du mot de passe (lien reçu par e-mail) : page sans mise en cache ni adresse de provenance, formulaire protégé par CSRF.
Route::middleware([EntetesPagePrivee::class, 'throttle:reinitialisation'])->prefix('reinitialiser-mot-de-passe')->controller(ReinitialisationMotDePassePageController::class)->group(function () {
    Route::get('/{jeton}', 'formulaire');
    Route::post('/{jeton}', 'enregistrer');
});
