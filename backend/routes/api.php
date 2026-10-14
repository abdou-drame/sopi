<?php

use App\Http\Controllers\Api\V1\Admin\AlertesController;
use App\Http\Controllers\Api\V1\Admin\AvisController as AdminAvisController;
use App\Http\Controllers\Api\V1\Admin\DecisionDemandeController;
use App\Http\Controllers\Api\V1\Admin\DemandePrestataireController;
use App\Http\Controllers\Api\V1\Admin\JustificatifController;
use App\Http\Controllers\Api\V1\Admin\ListeSanctionsController;
use App\Http\Controllers\Api\V1\Admin\LitigeController as AdminLitigeController;
use App\Http\Controllers\Api\V1\Admin\PaiementAdminController;
use App\Http\Controllers\Api\V1\Admin\ReservationAdminController;
use App\Http\Controllers\Api\V1\Admin\RetraitAdminController;
use App\Http\Controllers\Api\V1\Admin\RevenusController;
use App\Http\Controllers\Api\V1\Admin\SanctionController as AdminSanctionController;
use App\Http\Controllers\Api\V1\Admin\TableauDeBordController;
use App\Http\Controllers\Api\V1\Admin\UtilisateurController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Client\AgendaPrestataireController;
use App\Http\Controllers\Api\V1\Client\AvisPrestataireController;
use App\Http\Controllers\Api\V1\Client\AvisReservationController;
use App\Http\Controllers\Api\V1\Client\LitigeController as ClientLitigeController;
use App\Http\Controllers\Api\V1\Client\PaiementReservationController;
use App\Http\Controllers\Api\V1\Client\ProfilPublicController;
use App\Http\Controllers\Api\V1\Client\RecherchePrestatairesController;
use App\Http\Controllers\Api\V1\Client\ReservationController;
use App\Http\Controllers\Api\V1\Client\VerrouCreneauController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InscriptionPrestataireController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Prestataire\AgendaController;
use App\Http\Controllers\Api\V1\Prestataire\AvisController;
use App\Http\Controllers\Api\V1\Prestataire\LitigeController as PrestataireLitigeController;
use App\Http\Controllers\Api\V1\Prestataire\PhotoAtelierController;
use App\Http\Controllers\Api\V1\Prestataire\PrestationController;
use App\Http\Controllers\Api\V1\Prestataire\ReservationController as PrestataireReservationController;
use App\Http\Controllers\Api\V1\Prestataire\RetraitController;
use App\Http\Controllers\Api\V1\ProfilController;
use App\Http\Controllers\Api\V1\SuiviDossierController;
use App\Http\Controllers\Api\V1\WebhookPaiementController;
use Illuminate\Support\Facades\Route;

// Toutes les routes de ce fichier sont préfixées par /api (voir bootstrap/app.php).
Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::post('/prestataires/inscription', InscriptionPrestataireController::class)->middleware('throttle:inscription-prestataire');
    Route::get('/suivi/{jeton}', SuiviDossierController::class)->middleware('throttle:suivi-dossier');

    // Webhook du prestataire de paiement : public (la signature HMAC remplace l'authentification).
    Route::post('/webhooks/paiement', WebhookPaiementController::class)->middleware('throttle:webhook-paiement');

    // Administration : jeton Sanctum + rôle administrateur (alias 'admin', voir bootstrap/app.php).
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        // Tableau de bord (M11) : lecture seule.
        Route::get('/tableau-de-bord', [TableauDeBordController::class, 'index']);
        Route::get('/revenus', [RevenusController::class, 'index']);
        Route::get('/alertes', [AlertesController::class, 'index']);

        Route::get('/prestataires/demandes', [DemandePrestataireController::class, 'index']);
        Route::get('/prestataires/{id}', [DemandePrestataireController::class, 'show'])->whereUuid('id');
        Route::post('/prestataires/{id}/accepter', [DecisionDemandeController::class, 'accepter'])->whereUuid('id');
        Route::post('/prestataires/{id}/refuser', [DecisionDemandeController::class, 'refuser'])->whereUuid('id');
        Route::get('/justificatifs/{id}/telecharger', [JustificatifController::class, 'telecharger'])->whereUuid('id');

        // Litiges (M9.2) : consultation et décision motivée. Identifiant non contraint : un id invalide répond 404 en français.
        Route::get('/litiges', [AdminLitigeController::class, 'index']);
        Route::get('/litiges/{id}', [AdminLitigeController::class, 'show']);
        Route::get('/litiges/{id}/preuves/{preuve}', [AdminLitigeController::class, 'preuve'])->name('admin.litiges.preuve');
        Route::post('/litiges/{id}/trancher', [AdminLitigeController::class, 'trancher'])->middleware('throttle:litige');

        // Compléments de lecture (M12). Identifiant non contraint : un id invalide répond 404 en français.
        Route::get('/utilisateurs', [UtilisateurController::class, 'index']);
        Route::get('/utilisateurs/{id}', [UtilisateurController::class, 'show']);
        Route::get('/sanctions', ListeSanctionsController::class);
        Route::get('/reservations', [ReservationAdminController::class, 'index']);
        Route::get('/reservations/{id}', [ReservationAdminController::class, 'show']);
        Route::get('/paiements', [PaiementAdminController::class, 'index']);
        Route::get('/paiements/{id}', [PaiementAdminController::class, 'show']);
        Route::get('/retraits', [RetraitAdminController::class, 'index']);
        Route::get('/retraits/{id}', [RetraitAdminController::class, 'show']);

        Route::post('/utilisateurs/{id}/sanctions', [AdminSanctionController::class, 'store'])->middleware('throttle:sanction');
        Route::get('/utilisateurs/{id}/sanctions', [AdminSanctionController::class, 'index']);
        Route::post('/sanctions/{id}/lever', [AdminSanctionController::class, 'lever'])->middleware('throttle:sanction');

        // Modération des avis (M9.4).
        Route::get('/avis', [AdminAvisController::class, 'index']);
        Route::post('/avis/{id}/masquer', [AdminAvisController::class, 'masquer'])->middleware('throttle:sanction');
        Route::post('/avis/{id}/conserver', [AdminAvisController::class, 'conserver'])->middleware('throttle:sanction');
        Route::post('/avis/{id}/retablir', [AdminAvisController::class, 'retablir'])->middleware('throttle:sanction');
    });

    // Espace client : jeton Sanctum + rôle client.
    Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
        Route::get('/prestataires', RecherchePrestatairesController::class);
        Route::get('/prestataires/{id}', [ProfilPublicController::class, 'show']);
        Route::get('/prestataires/{id}/photos/{photo}', [ProfilPublicController::class, 'photo'])->name('prestataires.photo');
        Route::get('/prestataires/{id}/agenda', AgendaPrestataireController::class);
        Route::get('/prestataires/{id}/avis', [AvisPrestataireController::class, 'index']);

        // Verrou temporaire d'un créneau (RG08, RG09). Identifiant non contraint : un id invalide répond 404 en français.
        Route::middleware('throttle:verrou-creneau')->group(function () {
            Route::post('/creneaux/{id}/verrou', [VerrouCreneauController::class, 'verrouiller']);
            Route::delete('/creneaux/{id}/verrou', [VerrouCreneauController::class, 'liberer']);
        });

        // Réservation d'un créneau verrouillé (RF03, RF04).
        Route::post('/reservations', [ReservationController::class, 'store'])->middleware('throttle:reservation');

        // Suivi et annulation par le client (RF06, RF07). Pas de whereUuid : un identifiant invalide répond 404 en français.
        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::get('/reservations/{id}', [ReservationController::class, 'show']);
        Route::post('/reservations/{id}/annuler', [ReservationController::class, 'annuler'])->middleware('throttle:annulation-reservation');

        // Paiement de l'acompte d'une réservation acceptée (M6.3). Aucun corps ; identifiant non contraint (404 en français).
        Route::post('/reservations/{id}/payer', PaiementReservationController::class)->middleware('throttle:paiement-reservation');

        // Avis du client sur une réservation terminée (M8.2). Identifiant non contraint : un id invalide répond 404 en français.
        Route::post('/reservations/{id}/avis', [AvisReservationController::class, 'store'])->middleware('throttle:avis');

        // Litiges du client (M9.2).
        Route::post('/reservations/{id}/litiges', [ClientLitigeController::class, 'store'])->middleware('throttle:litige');
        Route::get('/litiges', [ClientLitigeController::class, 'index']);
        Route::get('/litiges/{id}', [ClientLitigeController::class, 'show']);
        Route::get('/litiges/{id}/preuves/{preuve}', [ClientLitigeController::class, 'preuve'])->name('client.litiges.preuve');
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
        Route::post('/reservations/{id}/demarrer', [PrestataireReservationController::class, 'demarrer'])->middleware('throttle:code-reservation');
        Route::post('/reservations/{id}/annuler', [PrestataireReservationController::class, 'annuler'])->middleware('throttle:reponse-reservation');
        Route::post('/reservations/{id}/terminer', [PrestataireReservationController::class, 'terminer'])->middleware('throttle:reponse-reservation');

        // Solde et retraits (M7). Pas de whereUuid : un identifiant invalide répond comme un retrait inconnu.
        Route::get('/solde', [RetraitController::class, 'solde']);
        Route::get('/retraits', [RetraitController::class, 'index']);
        Route::get('/retraits/{id}', [RetraitController::class, 'show']);
        Route::post('/retraits', [RetraitController::class, 'store'])->middleware('throttle:retrait');

        // Avis reçus (M8.3).
        Route::get('/avis', [AvisController::class, 'index']);
        Route::post('/avis/{id}/signaler', [AvisController::class, 'signaler'])->middleware('throttle:avis');

        // Litiges me visant (M9.2) : consultation et réponse unique.
        Route::get('/litiges', [PrestataireLitigeController::class, 'index']);
        Route::get('/litiges/{id}', [PrestataireLitigeController::class, 'show']);
        Route::get('/litiges/{id}/preuves/{preuve}', [PrestataireLitigeController::class, 'preuve'])->name('prestataire.litiges.preuve');
        Route::post('/litiges/{id}/reponse', [PrestataireLitigeController::class, 'repondre'])->middleware('throttle:litige');
    });

    Route::middleware('auth:sanctum')->prefix('me')->controller(ProfilController::class)->group(function () {
        Route::get('/', 'afficher');
        Route::put('/', 'modifier')->middleware('throttle:profil');
        Route::put('/mot-de-passe', 'changerMotDePasse')->middleware('throttle:mot-de-passe');
        Route::put('/jeton-push', 'enregistrerJetonPush')->middleware('throttle:jeton-push');
        Route::delete('/jeton-push', 'retirerJetonPush')->middleware('throttle:jeton-push');
    });

    // Notifications dans l'application (M10.3) : client et prestataire. L'administrateur n'en reçoit pas (403).
    Route::middleware(['auth:sanctum', 'role:client,prestataire', 'throttle:notifications'])->prefix('notifications')->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/non-lues/compteur', 'compteur');
        Route::post('/lire-tout', 'lireTout');
        Route::post('/{id}/lire', 'lire');
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
