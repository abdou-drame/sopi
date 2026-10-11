<?php

use App\Http\Controllers\Api\V1\Admin\DecisionDemandeController;
use App\Http\Controllers\Api\V1\Admin\DemandePrestataireController;
use App\Http\Controllers\Api\V1\Admin\JustificatifController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InscriptionPrestataireController;
use App\Http\Controllers\Api\V1\ProfilController;
use App\Http\Controllers\Api\V1\SuiviDossierController;
use Illuminate\Support\Facades\Route;

// Toutes les routes de ce fichier sont préfixées par /api (voir bootstrap/app.php).
Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::post('/prestataires/inscription', InscriptionPrestataireController::class)->middleware('throttle:inscription-prestataire');
    Route::get('/suivi/{jeton}', SuiviDossierController::class)->middleware('throttle:suivi-dossier');

    // Administration : jeton Sanctum + rôle administrateur (alias 'admin', voir bootstrap/app.php).
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        Route::get('/prestataires/demandes', [DemandePrestataireController::class, 'index']);
        Route::get('/prestataires/{id}', [DemandePrestataireController::class, 'show'])->whereUuid('id');
        Route::post('/prestataires/{id}/accepter', [DecisionDemandeController::class, 'accepter'])->whereUuid('id');
        Route::post('/prestataires/{id}/refuser', [DecisionDemandeController::class, 'refuser'])->whereUuid('id');
        Route::get('/justificatifs/{id}/telecharger', [JustificatifController::class, 'telecharger'])->whereUuid('id');
    });

    Route::middleware('auth:sanctum')->prefix('me')->controller(ProfilController::class)->group(function () {
        Route::get('/', 'afficher');
        Route::put('/', 'modifier')->middleware('throttle:profil');
        Route::put('/mot-de-passe', 'changerMotDePasse')->middleware('throttle:mot-de-passe');
        Route::put('/jeton-push', 'enregistrerJetonPush')->middleware('throttle:jeton-push');
    });

    Route::prefix('auth')->group(function () {
        Route::post('/inscription', [AuthController::class, 'inscription'])->middleware('throttle:inscription');
        Route::post('/connexion', [AuthController::class, 'connexion'])->middleware('throttle:connexion');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/deconnexion', [AuthController::class, 'deconnexion']);
            Route::get('/moi', [AuthController::class, 'moi']);
        });
    });
});
