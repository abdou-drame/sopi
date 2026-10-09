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

        // Anti-balayage par adresse IP. Le blocage par numéro (5 échecs => 15 min) est géré
        // par BlocageConnexionService.
        RateLimiter::for('connexion', fn (Request $request) => Limit::perMinute(config('securite.connexion.max_par_minute_par_ip'))
            ->by($request->ip()));
    }
}
