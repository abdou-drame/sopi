<?php

namespace App\Console\Commands;

use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

/** Envoie (ou renvoie) par lots les notifications en attente (M10.1). Planifiée toutes les minutes (routes/console.php). N'affiche qu'un nombre. */
class EnvoyerNotificationsEnAttente extends Command
{
    protected $signature = 'sopi:notifications:envoyer-en-attente';

    protected $description = 'Envoie les notifications e-mail et push en attente, avec reprise des échecs';

    public function handle(NotificationService $service): int
    {
        $nombre = $service->envoyerEnAttente((int) config('sopi.notifications.taille_lot'));
        $this->info("$nombre notification(s) traitée(s).");

        return self::SUCCESS;
    }
}
