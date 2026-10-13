<?php

namespace App\Services;

use App\Exceptions\RetraitEchoueException;
use App\Models\Prestataire;
use App\Models\Retrait;
use App\Services\Paiement\PaiementService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Solde et retraits d'un prestataire (RF23, RG28 à RG33) : consultation (M7.3), demande de retrait (M7.4). */
class RetraitService
{
    public const PAR_PAGE = 20;

    public function __construct(private readonly PaiementService $paiements) {}

    /** Retraits du prestataire, du plus récent au plus ancien. */
    public function lister(Prestataire $prestataire): LengthAwarePaginator
    {
        return $prestataire->retraits()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE);
    }

    /** Détail d'un retrait, ou null s'il n'existe pas OU n'appartient pas à ce prestataire (même réponse, aucune fuite). */
    public function trouver(Prestataire $prestataire, string $id): ?Retrait
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return $prestataire->retraits()->find($id);
    }

    /**
     * Demande de retrait. Ordre voulu : (a) débit atomique + création du retrait dans une courte transaction, (b) appel du
     * prestataire de paiement HORS transaction, (c) succès ou (d) échec définitif traités par mises à jour conditionnelles.
     *
     * - Échec DÉFINITIF = le prestataire répond « refusé » (ResultatOperation->succes === false) : retrait ECHOUE, solde recrédité
     *   une seule fois, puis RetraitEchoueException (503).
     * - Résultat AMBIGU = toute exception (délai dépassé, panne, réponse illisible, et aussi une panne d'enregistrement après un
     *   versement accepté) : AUCUN recrédit, le retrait reste EN_COURS (montant déjà débité), car le versement a pu partir.
     *   La commande de signalement (M7.5) le fait remonter.
     *
     * @throws ValidationException solde insuffisant (clé « montant »)
     * @throws RetraitEchoueException refus définitif du prestataire de paiement
     */
    public function demander(Prestataire $prestataire, int $montant, string $telephone): Retrait
    {
        $retrait = DB::transaction(function () use ($prestataire, $montant, $telephone) {
            // Jamais « lire puis écrire en PHP » : la condition est dans l'UPDATE, donc deux demandes simultanées ne dépassent pas le solde.
            $debites = Prestataire::query()
                ->whereKey($prestataire->getKey())
                ->where('solde', '>=', $montant)
                ->update(['solde' => DB::raw('solde - '.$montant)]);

            if ($debites !== 1) {
                throw ValidationException::withMessages(['montant' => ['Solde insuffisant.']]);
            }

            return Retrait::ouvrir($prestataire->getKey(), $montant, $telephone);
        });

        try {
            $resultat = $this->paiements->verser(number_format($montant, 2, '.', ''), $telephone);

            if ($resultat->succes) {
                $retrait->reussirSiEnCours($resultat->reference);

                return $retrait;
            }
        } catch (Throwable $e) {
            return $this->resterEnCours($retrait, $e);
        }

        try {
            $this->recrediterApresEchec($retrait);
        } catch (Throwable $e) {
            return $this->resterEnCours($retrait, $e);
        }

        throw new RetraitEchoueException;
    }

    /** ECHOUE puis recrédit, dans UNE transaction ; la garde recredite_le garantit un seul recrédit quoi qu'il arrive. */
    private function recrediterApresEchec(Retrait $retrait): void
    {
        DB::transaction(function () use ($retrait) {
            if (! $retrait->echouerSiEnCours() || ! $retrait->recrediterSiPossible()) {
                return;
            }

            Prestataire::query()
                ->whereKey($retrait->prestataire_id)
                ->update(['solde' => DB::raw('solde + '.(int) $retrait->montant)]);
        });
    }

    /** Résultat ambigu : pas de recrédit ; journal sans donnée sensible (ni numéro, ni montant) ; le retrait reste EN_COURS. */
    private function resterEnCours(Retrait $retrait, Throwable $e): Retrait
    {
        Log::error('Retrait au résultat incertain : laissé en cours, aucun recrédit.', [
            'retrait_id' => $retrait->id,
            'exception' => $e::class,
        ]);

        return $retrait->refresh();
    }
}
