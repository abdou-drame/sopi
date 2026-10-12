<?php

use App\Http\Controllers\Api\V1\Admin\DecisionDemandeController;
use App\Http\Controllers\Api\V1\Admin\DemandePrestataireController;
use App\Http\Controllers\Api\V1\Admin\JustificatifController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Client\AgendaPrestataireController;
use App\Http\Controllers\Api\V1\Client\ProfilPublicController;
use App\Http\Controllers\Api\V1\Client\RecherchePrestatairesController;
use App\Http\Controllers\Api\V1\Client\ReservationController;
use App\Http\Controllers\Api\V1\Client\VerrouCreneauController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InscriptionPrestataireController;
use App\Http\Controllers\Api\V1\Prestataire\AgendaController;
use App\Http\Controllers\Api\V1\Prestataire\PhotoAtelierController;
use App\Http\Controllers\Api\V1\Prestataire\PrestationController;
use App\Http\Controllers\Api\V1\Prestataire\ReservationController as PrestataireReservationController;
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

    // Espace client : jeton Sanctum + rôle client.
    Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
        Route::get('/prestataires', RecherchePrestatairesController::class);
        Route::get('/prestataires/{id}', [ProfilPublicController::class, 'show']);
        Route::get('/prestataires/{id}/photos/{photo}', [ProfilPublicController::class, 'photo'])->name('prestataires.photo');
        Route::get('/prestataires/{id}/agenda', AgendaPrestataireController::class);

        // Verrou temporaire d'un créneau (RG08, RG09). Identifiant non contraint : un id invalide répond 404 en français.
        Route::middleware('throttle:verrou-creneau')->group(function () {
            Route::post('/creneaux/{id}/verrou', [VerrouCreneauController::class, 'verrouiller']);
            Route::delete('/creneaux/{id}/verrou', [VerrouCreneauController::class, 'liberer']);
        });

        // Réservation d'un créneau verrouillé (RF03, RF04).
        Route::post('/reservations', [ReservationController::class, 'store'])->middleware('throttle:reservation');
    });

    // Espace prestataire : jeton Sanctum + rôle prestataire (alias 'prestataire'). Aucune route de suppression.
    Route::middleware(['auth:sanctum', 'prestataire'])->prefix('prestataire')->group(function () {
        Route::get('/prestations', [PrestationController::class, 'index']);
        Route::post('/prestations', [PrestationController::class, 'store']);
        Route::patch('/prestations/{id}', [PrestationController::class, 'update'])->whereUuid('id');

        Route::get('/photos', [PhotoAtelierController::class, 'index']);
        Route::get('/photos/{photo}', [PhotoAtelierController::class, 'afficher'])->whereUuid('photo')->name('prestataire.photo');
        Route::post('/photos', [PhotoAtelierController::class, 'store']);
        Route::delete('/photos/{photo}', [PhotoAtelierController::class, 'destroy'])->whereUuid('photo');

        Route::get('/agenda', [AgendaController::class, 'consulter']);
        Route::post('/agenda/plages', [AgendaController::class, 'configurer']);
        Route::post('/agenda/indisponibilites', [AgendaController::class, 'declarerIndisponibilite']);
        Route::delete('/agenda/creneaux/{id}', [AgendaController::class, 'supprimerCreneau'])->whereUuid('id');

        // Demandes de réservation reçues (pas de whereUuid : un identifiant invalide répond comme un inconnu).
        Route::get('/reservations', [PrestataireReservationController::class, 'index']);
        Route::get('/reservations/{id}', [PrestataireReservationController::class, 'show']);
        Route::post('/reservations/{id}/accepter', [PrestataireReservationController::class, 'accepter'])->middleware('throttle:reponse-reservation');
        Route::post('/reservations/{id}/refuser', [PrestataireReservationController::class, 'refuser'])->middleware('throttle:reponse-reservation');
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
