<?php

namespace App\Services;

use App\Enums\StatutPaiement;
use App\Enums\TypeMouvementPortefeuille;
use App\Exceptions\CreditSoldeImpossibleException;
use App\Models\MouvementPortefeuille;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Services\Paiement\CalculPaiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Crédit du solde du prestataire à la fin d'une prestation (M7.2, RG26) : montant de l'acompte moins la commission du
 * paiement REUSSI. Une seule fois par paiement (garde paiements.credite_le), en deux UPDATE atomiques dans la même
 * transaction, sans jamais lire puis écrire le solde en PHP. À appeler dans la transaction de « terminer ».
 *
 * Service dédié plutôt qu'une méthode de DeroulementReservationService ou de PaiementService : le portefeuille
 * (crédit, puis retraits en M7.4) a sa propre logique, séparée du déroulement et de l'encaissement.
 */
class CreditSoldeService
{
    public const LIBELLE_PRESTATION_TERMINEE = 'Prestation terminée (acompte moins commission)';

    /** Annulation par le client d'une réservation payée : l'acompte n'est pas remboursé, le prestataire reçoit acompte moins commission. */
    public const LIBELLE_ANNULATION_CLIENT = 'Annulation par le client (acompte moins commission)';

    /**
     * @return bool vrai si le solde a été crédité par CET appel ; faux si déjà crédité, ou sans paiement réussi
     *
     * @throws CreditSoldeImpossibleException si la base échoue (la transaction englobante doit alors être annulée)
     */
    public function crediter(Reservation $reservation): bool
    {
        return $this->crediterAvecDetail($reservation) !== null;
    }

    /**
     * Comme crediter(), mais renvoie ce qui a été crédité (montants et solde lus dans la transaction), ou null si rien ne l'a été.
     *
     * @throws CreditSoldeImpossibleException si la base échoue (la transaction englobante doit alors être annulée)
     */
    public function crediterAvecDetail(Reservation $reservation, string $libelle = self::LIBELLE_PRESTATION_TERMINEE): ?CreditEffectue
    {
        $paiement = Paiement::query()
            ->where('reservation_id', $reservation->id)
            ->where('statut', StatutPaiement::Reussi->value)
            ->first();

        if ($paiement === null) {
            // Cas théorique : une réservation n'est EN_COURS qu'après un paiement réussi. La fin de prestation n'échoue pas.
            Log::warning('Crédit du solde impossible : aucun paiement réussi pour la réservation.', ['reservation_id' => $reservation->id]);

            return null;
        }

        try {
            return DB::transaction(fn () => $this->crediterPaiement($paiement, $reservation->creneau->prestataire_id, $libelle));
        } catch (Throwable $e) {
            Log::error('Échec du crédit du solde du prestataire.', ['paiement_id' => $paiement->id, 'erreur' => $e::class]);

            throw new CreditSoldeImpossibleException('Crédit du solde impossible.', 0, $e);
        }
    }

    private function crediterPaiement(Paiement $paiement, string $prestataireId, string $libelle): ?CreditEffectue
    {
        // Garde : un seul appel gagne, même en concurrence. 0 ligne = déjà crédité (ou paiement plus « réussi »).
        if (! $paiement->crediterSiPossible()) {
            return null;
        }

        // Calcul exact en centimes entiers (jamais de flottant) ; l'ajout est une opération SQL atomique.
        $part = CalculPaiement::montantCredite($paiement->montant_acompte, $paiement->commission);
        Prestataire::query()->whereKey($prestataireId)->update(['solde' => DB::raw('solde + '.$part)]);
        $solde = Prestataire::query()->whereKey($prestataireId)->value('solde');
        MouvementPortefeuille::enregistrer($prestataireId, TypeMouvementPortefeuille::Credit, $part, $libelle, $solde, reservationId: $paiement->reservation_id);

        return new CreditEffectue((string) $paiement->montant_acompte, (string) $paiement->commission, $part, (string) $solde);
    }
}
