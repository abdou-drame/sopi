<?php

namespace App\Console\Commands;

use App\Services\ExpirationService;
use Illuminate\Console\Command;

/** RG09. Planifiée chaque minute (routes/console.php). N'affiche qu'un nombre, aucune donnée personnelle. */
class LibererVerrousCreneaux extends Command
{
    protected $signature = 'sopi:creneaux:liberer-verrous';

    protected $description = 'Libère les créneaux dont le verrou temporaire a expiré';

    public function handle(ExpirationService $service): int
    {
        $nombre = $service->libererVerrousExpires();
        $this->info("$nombre créneau(x) libéré(s).");

        return self::SUCCESS;
    }
}
