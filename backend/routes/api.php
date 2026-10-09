<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

// Toutes les routes de ce fichier sont préfixées par /api (voir bootstrap/app.php).
Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::prefix('auth')->group(function () {
        Route::post('/inscription', [AuthController::class, 'inscription'])->middleware('throttle:inscription');
        Route::post('/connexion', [AuthController::class, 'connexion'])->middleware('throttle:connexion');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/deconnexion', [AuthController::class, 'deconnexion']);
            Route::get('/moi', [AuthController::class, 'moi']);
        });
    });
});
