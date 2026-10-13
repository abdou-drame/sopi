<?php

namespace App\Console\Commands;

use App\Enums\StatutRetrait;
use App\Models\Retrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Signalement des retraits restés EN_COURS trop longtemps (M7.5), planifié toutes les 10 minutes (routes/console.php).
 * Lecture seule : ne modifie RIEN en base (ni statut, ni solde). Un retrait bloqué a pu être versé sans que le résultat soit
 * connu : il est traité à la main. Un avertissement par retrait, avec son seul identifiant (jamais de numéro ni de montant).
 */
class SignalerRetraitsBloques extends Command
{
    protected $signature = 'sopi:retraits:signaler-bloques';

    protected $description = 'Signale (journal) les retraits restés en cours au-delà du délai configuré, sans rien modifier';

    public function handle(): int
    {
        $limite = now()->subMinutes((int) config('sopi.retraits.delai_blocage_minutes'));

        $ids = Retrait::query()
            ->where('statut', StatutRetrait::EnCours->value)
            ->where('created_at', '<=', $limite)
            ->orderBy('created_at')
            ->pluck('id');

        foreach ($ids as $id) {
            Log::warning('Retrait bloqué en cours : intervention manuelle requise.', ['retrait_id' => $id]);
        }

        $this->info($ids->count().' retrait(s) bloqué(s) signalé(s).');

        return self::SUCCESS;
    }
}
