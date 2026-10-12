<?php

namespace App\Console\Commands;

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Services\RemboursementPaiementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reprise des remboursements en échec (M6.5). Planifiée toutes les 5 minutes (routes/console.php). Ne sélectionne que les
 * paiements marqués « dus » (remboursement_du_le), REUSSI ou ECHOUE, sous le maximum d'essais et dont le dernier essai
 * remonte à au moins le délai configuré. N'affiche et ne journalise que des nombres et des identifiants de paiement.
 */
class RembourserPaiementsDus extends Command
{
    public const TAILLE_LOT = 100;

    protected $signature = 'sopi:paiements:rembourser-dus';

    protected $description = 'Reprend le remboursement des paiements marqués à rembourser (échecs précédents)';

    public function handle(RemboursementPaiementService $service): int
    {
        $maximum = (int) config('sopi.paiement.remboursement_max_tentatives');
        $limite = now()->subMinutes((int) config('sopi.paiement.remboursement_delai_minutes'));

        $traites = $reussis = $echoues = 0;

        $lot = Paiement::query()
            ->whereIn('statut', [StatutPaiement::Reussi->value, StatutPaiement::Echoue->value])
            ->whereNotNull('remboursement_du_le')
            ->where('remboursement_tentatives', '<', $maximum)
            ->where('updated_at', '<=', $limite)
            ->orderBy('remboursement_du_le')
            ->limit(self::TAILLE_LOT)
            ->get();

        foreach ($lot as $paiement) {
            try {
                // Compte l'essai AVANT l'appel, par mise à jour conditionnelle : un autre processus ne le reprend pas en parallèle.
                if (! $paiement->compterTentativeRemboursement($maximum)) {
                    continue;
                }
                $traites++;
                $service->rembourser($paiement, StatutPaiement::Reussi, StatutPaiement::Echoue) ? $reussis++ : $echoues++;
            } catch (Throwable $e) {
                $echoues++;
                Log::error('Reprise du remboursement impossible.', ['paiement_id' => $paiement->id, 'erreur' => $e::class]);
            }
        }

        $abandonnes = Paiement::query()
            ->whereIn('statut', [StatutPaiement::Reussi->value, StatutPaiement::Echoue->value])
            ->whereNotNull('remboursement_du_le')
            ->where('remboursement_tentatives', '>=', $maximum)
            ->pluck('id');
        foreach ($abandonnes as $id) {
            Log::error('Remboursement : intervention manuelle requise.', ['paiement_id' => $id]);
        }

        $this->info("$traites remboursement(s) repris : $reussis réussi(s), $echoues échoué(s).");
        if ($abandonnes->isNotEmpty()) {
            $this->warn($abandonnes->count().' paiement(s) nécessitent une intervention manuelle.');
        }

        return self::SUCCESS;
    }
}
