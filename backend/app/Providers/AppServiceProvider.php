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

        // 5 tentatives de connexion par minute et par couple téléphone + adresse IP.
        RateLimiter::for('connexion', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('telephone')).'|'.$request->ip()));
    }
}
