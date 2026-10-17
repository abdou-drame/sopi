<?php

namespace App\Services;

use App\Enums\OperateurRetrait;
use App\Enums\StatutRetrait;
use App\Enums\TypeMouvementPortefeuille;
use App\Exceptions\RetraitEchoueException;
use App\Models\MouvementPortefeuille;
use App\Models\Prestataire;
use App\Models\Retrait;
use App\Services\Paiement\ContexteVersement;
use App\Services\Paiement\DexPay\JournalDexPay;
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

    /** Mouvements du solde du prestataire, du plus récent au plus ancien. */
    public function mouvements(Prestataire $prestataire): LengthAwarePaginator
    {
        return $prestataire->mouvementsPortefeuille()
            ->with(['reservation.prestation:id,nom', 'retrait:id,statut,operateur'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE);
    }

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
    public function demander(Prestataire $prestataire, int $montant, string $telephone, OperateurRetrait $operateur): Retrait
    {
        $retrait = DB::transaction(function () use ($prestataire, $montant, $telephone, $operateur) {
            // Jamais « lire puis écrire en PHP » : la condition est dans l'UPDATE, donc deux demandes simultanées ne dépassent pas le solde.
            $debites = Prestataire::query()
                ->whereKey($prestataire->getKey())
                ->where('solde', '>=', $montant)
                ->update(['solde' => DB::raw('solde - '.$montant)]);

            if ($debites !== 1) {
                throw ValidationException::withMessages(['montant' => ['Solde insuffisant.']]);
            }

            $retrait = Retrait::ouvrir($prestataire->getKey(), $montant, $telephone, $operateur);
            MouvementPortefeuille::enregistrer(
                $prestataire->getKey(), TypeMouvementPortefeuille::Retrait, $montant, 'Demande de retrait',
                Prestataire::query()->whereKey($prestataire->getKey())->value('solde'), retraitId: $retrait->id,
            );

            return $retrait;
        });

        $contexteEchec = [];
        try {
            $resultat = $this->paiements->verser(number_format($montant, 2, '.', ''), $telephone, new ContexteVersement($retrait->id, $operateur));

            if ($resultat->succes && ! $resultat->enCours) {
                $retrait->reussirSiEnCours($resultat->reference);

                return $retrait;
            }
            if ($resultat->succes) {
                // Versement accepté mais pas encore terminé : le retrait reste EN_COURS (montant débité), le webhook ou la réconciliation conclura.
                if ($resultat->reference !== null) {
                    $retrait->enregistrerReferenceSiEnCours($resultat->reference);
                }

                return $retrait->refresh();
            }
            $contexteEchec = JournalDexPay::erreur(
                'dexpay.versement_echec',
                'Retrait refusé par le prestataire de paiement : à vérifier (solde marchand, plafonds, compte).',
                ['retrait_id' => $retrait->id, 'motif' => $resultat->motif],
                null,
                $resultat->statutHttp,
                $resultat->extrait,
            );
        } catch (Throwable $e) {
            return $this->resterEnCours($retrait, $e);
        }

        try {
            $this->recrediterApresEchec($retrait);
        } catch (Throwable $e) {
            return $this->resterEnCours($retrait, $e);
        }

        throw new RetraitEchoueException(JournalDexPay::diagnostic($contexteEchec));
    }

    /**
     * Applique le résultat final d'un versement connu par le webhook ou la réconciliation. Idempotent : seules des mises à jour
     * conditionnelles depuis EN_COURS changent le statut, et le recrédit n'a lieu qu'une fois (garde recredite_le).
     *
     * @return bool vrai si ce passage a changé le retrait
     */
    public function appliquerResultat(Retrait $retrait, StatutRetrait $statut, ?string $reference = null): bool
    {
        if ($statut === StatutRetrait::Reussi) {
            if ($retrait->reussirSiEnCours($reference ?? $retrait->reference_dexpay)) {
                return true;
            }
            if ($retrait->refresh()->statut === StatutRetrait::Echoue) {
                Log::error('Versement terminé chez le prestataire alors que le retrait est ECHOUE : à vérifier à la main.', ['retrait_id' => $retrait->id]);
            }

            return false;
        }
        if ($statut !== StatutRetrait::Echoue) {
            return false;
        }

        $change = false;
        DB::transaction(function () use ($retrait, &$change) {
            $change = $retrait->echouerSiEnCours();
            if ($retrait->recrediterSiPossible()) {
                $this->recrediter($retrait);
            }
        });

        return $change;
    }

    /** ECHOUE puis recrédit, dans UNE transaction ; la garde recredite_le garantit un seul recrédit quoi qu'il arrive. */
    private function recrediterApresEchec(Retrait $retrait): void
    {
        DB::transaction(function () use ($retrait) {
            if (! $retrait->echouerSiEnCours() || ! $retrait->recrediterSiPossible()) {
                return;
            }

            $this->recrediter($retrait);
        });
    }

    /** Recrédit du montant d'un retrait échoué et ligne du journal, à appeler dans la transaction de l'échec (après la garde recredite_le). */
    private function recrediter(Retrait $retrait): void
    {
        Prestataire::query()
            ->whereKey($retrait->prestataire_id)
            ->update(['solde' => DB::raw('solde + '.(int) $retrait->montant)]);

        MouvementPortefeuille::enregistrer(
            $retrait->prestataire_id, TypeMouvementPortefeuille::RemboursementRetrait, (int) $retrait->montant, 'Retrait échoué : montant recrédité',
            Prestataire::query()->whereKey($retrait->prestataire_id)->value('solde'), retraitId: $retrait->id,
        );
    }

    /** Résultat ambigu : pas de recrédit ; journal sans donnée sensible (ni numéro, ni montant) ; le retrait reste EN_COURS. */
    private function resterEnCours(Retrait $retrait, Throwable $e): Retrait
    {
        JournalDexPay::erreur('dexpay.versement_echec', 'Retrait au résultat incertain : laissé en cours, aucun recrédit.', ['retrait_id' => $retrait->id], $e);

        return $retrait->refresh();
    }
}
