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

// Reprise des remboursements en échec (M6.5), toutes les 5 minutes.
Schedule::command('sopi:paiements:rembourser-dus')->everyFiveMinutes()->withoutOverlapping();

// Signalement des retraits restés en cours trop longtemps (M7.5), toutes les 10 minutes. Lecture seule.
Schedule::command('sopi:retraits:signaler-bloques')->everyTenMinutes()->withoutOverlapping();

// Levée automatique des suspensions arrivées à échéance (M9.3), toutes les 5 minutes.
Schedule::command('sopi:sanctions:lever-expirees')->everyFiveMinutes()->withoutOverlapping();

// Envoi et reprise des notifications e-mail et push en attente (M10), chaque minute.
Schedule::command('sopi:notifications:envoyer-en-attente')->everyMinute()->withoutOverlapping();
