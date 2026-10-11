<?php

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
