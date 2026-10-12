<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tâches planifiées des réservations et des verrous (RG09 à RG11), chaque minute. Le scheduler lui-même est lancé
// par « php artisan schedule:run » chaque minute (voir le rapport M5.6 : à configurer côté Dokploy).
Schedule::command('sopi:reservations:expirer-sans-reponse')->everyMinute()->withoutOverlapping();
Schedule::command('sopi:reservations:expirer-impayees')->everyMinute()->withoutOverlapping();
Schedule::command('sopi:creneaux:liberer-verrous')->everyMinute()->withoutOverlapping();
