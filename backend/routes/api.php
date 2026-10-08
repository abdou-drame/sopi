<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

// Toutes les routes de ce fichier sont préfixées par /api (voir bootstrap/app.php).
Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);
});
