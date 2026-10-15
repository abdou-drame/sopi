<?php

namespace App\Console\Commands;

use App\Enums\StatutRetrait;
use App\Exceptions\ConfigurationPaiementInvalide;
use App\Models\Retrait;
use App\Services\Paiement\ContexteVersement;
use App\Services\Paiement\PaiementService;
use App\Services\RetraitService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Réconciliation des retraits restés EN_COURS au-delà du délai de blocage (toutes les 10 minutes, routes/console.php).
 * Interroge le prestataire de paiement (GET /payouts/{reference}) puis applique les mêmes transitions que le webhook :
 * REUSSI, ou ECHOUE avec un seul recrédit. Un statut encore en cours ou une réponse illisible ne change rien : le retrait reste
 * signalé par sopi:retraits:signaler-bloques. Ne journalise que des identifiants.
 */
class ReconcilierRetraits extends Command
{
    protected $signature = 'sopi:retraits:reconcilier';

    protected $description = 'Interroge DexPay pour les retraits restés en cours trop longtemps et applique le résultat connu';

    public function handle(RetraitService $retraits): int
    {
        try {
            $prestataire = app(PaiementService::class);
        } catch (ConfigurationPaiementInvalide) {
            $this->error('Pilote de paiement mal configuré : réconciliation impossible.');

            return self::FAILURE;
        }

        $limite = now()->subMinutes((int) config('sopi.retraits.delai_blocage_minutes'));
        $traites = 0;

        Retrait::query()
            ->where('statut', StatutRetrait::EnCours->value)
            ->where('created_at', '<=', $limite)
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->each(function (Retrait $retrait) use ($prestataire, $retraits, &$traites) {
                try {
                    $statut = $prestataire->consulterVersement(ContexteVersement::referenceDe($retrait->id));
                    if ($statut === null || $statut === StatutRetrait::EnCours) {
                        return;
                    }
                    if ($retraits->appliquerResultat($retrait, $statut)) {
                        $traites++;
                    }
                } catch (Throwable $e) {
                    Log::error('Réconciliation d\'un retrait impossible.', ['retrait_id' => $retrait->id, 'exception' => $e::class]);
                }
            });

        $this->info($traites.' retrait(s) réconcilié(s).');

        return self::SUCCESS;
    }
}
