<?php

namespace App\Services;

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Services\Paiement\PaiementService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Remboursement de l'acompte d'un paiement (M6.4, réutilisable par M6.5). À appeler HORS de toute transaction : l'appel au
 * prestataire de paiement est externe. Un échec ne lève jamais d'exception : le paiement garde son statut et le journal
 * reçoit seulement l'identifiant du paiement et le type d'erreur (ni corps, ni secret). Aucune reprise automatique ici.
 */
class RemboursementPaiementService
{
    /**
     * Rembourse le montant intégral de l'acompte puis passe le paiement à REMBOURSE (mise à jour conditionnelle sur un des
     * statuts $depuis, REUSSI par défaut). Idempotent : un paiement déjà REMBOURSE n'est jamais remboursé deux fois.
     *
     * @return bool vrai si le paiement est REMBOURSE à l'issue de l'appel
     */
    public function rembourser(Paiement $paiement, StatutPaiement ...$depuis): bool
    {
        $paiement->refresh();
        if ($paiement->statut === StatutPaiement::Rembourse) {
            return true;
        }
        $depuis = $depuis ?: [StatutPaiement::Reussi];
        if (! in_array($paiement->statut, $depuis, true) || $paiement->reference_dexpay === null) {
            return false;
        }

        try {
            $resultat = app(PaiementService::class)->rembourser($paiement->reference_dexpay, $paiement->montant_acompte);
        } catch (Throwable $e) {
            $this->journaliser($paiement, $e::class);

            return false;
        }

        if (! $resultat->succes) {
            $this->journaliser($paiement, 'remboursement refusé');

            return false;
        }

        // Si la mise à jour ne passe pas, un autre traitement a déjà remboursé ce paiement.
        $paiement->rembourserSiStatut(...$depuis);

        return $paiement->refresh()->statut === StatutPaiement::Rembourse;
    }

    private function journaliser(Paiement $paiement, string $erreur): void
    {
        Log::error('Échec du remboursement d\'un paiement.', ['paiement_id' => $paiement->id, 'erreur' => $erreur]);
    }
}
