<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 5 inscriptions par minute et par adresse IP.
        RateLimiter::for('inscription', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('inscription-prestataire', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('suivi-dossier', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Profil : limites par utilisateur connecté (par IP en secours). Le changement de mot de passe
        // est le plus strict (protège l'ancien mot de passe contre les essais répétés avec un jeton volé).
        $parUtilisateur = fn (Request $request) => $request->user()?->id ?? $request->ip();
        RateLimiter::for('mot-de-passe', fn (Request $request) => Limit::perMinute(5)->by($parUtilisateur($request)));
        RateLimiter::for('profil', fn (Request $request) => Limit::perMinute(30)->by($parUtilisateur($request)));
        RateLimiter::for('verrou-creneau', fn (Request $request) => Limit::perMinute(20)->by($parUtilisateur($request)));
        RateLimiter::for('reservation', fn (Request $request) => Limit::perMinute(20)->by($parUtilisateur($request)));
        RateLimiter::for('reponse-reservation', fn (Request $request) => Limit::perMinute(30)->by($parUtilisateur($request)));
        RateLimiter::for('jeton-push', fn (Request $request) => Limit::perMinute(30)->by($parUtilisateur($request)));

        // Anti-balayage par adresse IP. Le blocage par numéro (5 échecs => 15 min) est géré
        // par BlocageConnexionService.
        RateLimiter::for('connexion', fn (Request $request) => Limit::perMinute(config('securite.connexion.max_par_minute_par_ip'))
            ->by($request->ip()));
    }
}
