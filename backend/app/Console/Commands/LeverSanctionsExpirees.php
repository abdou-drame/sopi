<?php

namespace App\Console\Commands;

use App\Services\SanctionService;
use Illuminate\Console\Command;

/** Levée automatique des suspensions arrivées à échéance (M9.3). Planifiée toutes les 5 minutes (routes/console.php). N'affiche qu'un nombre. */
class LeverSanctionsExpirees extends Command
{
    protected $signature = 'sopi:sanctions:lever-expirees';

    protected $description = 'Lève les suspensions temporaires arrivées à échéance et réactive les comptes';

    public function handle(SanctionService $service): int
    {
        $nombre = $service->leverExpirees();
        $this->info("$nombre sanction(s) levée(s).");

        return self::SUCCESS;
    }
}
