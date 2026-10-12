<?php

namespace App\Console\Commands;

use App\Services\ExpirationService;
use Illuminate\Console\Command;

/** RG11. Planifiée chaque minute (routes/console.php). N'affiche qu'un nombre, aucune donnée personnelle. */
class ExpirerReservationsImpayees extends Command
{
    protected $signature = 'sopi:reservations:expirer-impayees';

    protected $description = 'Annule les réservations ACCEPTEE non payées après le délai de paiement et libère leur créneau';

    public function handle(ExpirationService $service): int
    {
        $nombre = $service->expirerImpayees();
        $this->info("$nombre réservation(s) annulée(s) faute de paiement.");

        return self::SUCCESS;
    }
}
