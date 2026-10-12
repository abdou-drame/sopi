<?php

namespace App\Console\Commands;

use App\Services\ExpirationService;
use Illuminate\Console\Command;

/** RG10. Planifiée chaque minute (routes/console.php). N'affiche qu'un nombre, aucune donnée personnelle. */
class ExpirerReservationsSansReponse extends Command
{
    protected $signature = 'sopi:reservations:expirer-sans-reponse';

    protected $description = 'Annule les demandes EN_ATTENTE sans réponse du prestataire après le délai de réponse et libère leur créneau';

    public function handle(ExpirationService $service): int
    {
        $nombre = $service->expirerSansReponse();
        $this->info("$nombre réservation(s) annulée(s) faute de réponse du prestataire.");

        return self::SUCCESS;
    }
}
