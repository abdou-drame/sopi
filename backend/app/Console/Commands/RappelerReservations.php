<?php

namespace App\Console\Commands;

use App\Services\RappelRendezVousService;
use Illuminate\Console\Command;

/** RF20. Planifiée chaque minute (routes/console.php). N'affiche qu'un nombre, aucune donnée personnelle. */
class RappelerReservations extends Command
{
    protected $signature = 'sopi:reservations:rappeler';

    protected $description = 'Envoie un rappel (push et e-mail) au client et au prestataire des réservations confirmées qui commencent dans 2 heures au plus';

    public function handle(RappelRendezVousService $service): int
    {
        $nombre = $service->rappeler();
        $this->info("$nombre rappel(s) de rendez-vous envoyé(s).");

        return self::SUCCESS;
    }
}
