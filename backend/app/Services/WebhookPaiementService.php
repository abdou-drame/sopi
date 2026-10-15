<?php

namespace App\Services;

use App\Enums\StatutPaiement;
use App\Enums\StatutRetrait;
use App\Events\Reservation\ReservationConfirmee;
use App\Exceptions\ConfigurationPaiementInvalide;
use App\Exceptions\EvenementPaiementIllisible;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\Retrait;
use App\Services\Paiement\CalculPaiement;
use App\Services\Paiement\ContexteVersement;
use App\Services\Paiement\DexPay\JournalDexPay;
use App\Services\Paiement\EvenementPaiement;
use App\Services\Paiement\GenreEvenement;
use App\Services\Paiement\PaiementService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Webhook du prestataire de paiement (M6.4). C'est lui, et lui seul, qui confirme une réservation : paiement REUSSI,
 * réservation ACCEPTEE vers CONFIRMEE, code de réservation généré. Aucune confiance au client.
 *
 * Ordre : signature (rien d'autre n'est lu avant), analyse, référence connue, montant exact. Traitement idempotent.
 * Ne journalise jamais le corps, la signature, le secret ni le code : seulement des identifiants et des types d'erreur.
 */
class WebhookPaiementService
{
    /** Nombre maximal d'essais en cas de collision du code de réservation (index unique). */
    public const ESSAIS_CODE = 5;

    public function __construct(
        private readonly RemboursementPaiementService $remboursements,
        private readonly GenerateurCodeReservation $generateur,
    ) {}

    /**
     * @return array{0: int, 1: string} code HTTP et message
     */
    public function traiter(string $corpsBrut, ?string $signature): array
    {
        try {
            $prestataire = app(PaiementService::class);
        } catch (ConfigurationPaiementInvalide $e) {
            JournalDexPay::erreur('webhook.configuration_invalide', 'Webhook de paiement impossible : pilote mal configuré.', [], $e);

            return [Response::HTTP_SERVICE_UNAVAILABLE, 'Le service de paiement est momentanément indisponible.'];
        }

        if (! $prestataire->verifierSignature($corpsBrut, $signature)) {
            // Ni le corps, ni la signature reçue : le rejet seul est signalé.
            Log::warning('webhook.signature_rejetee : signature du webhook de paiement refusée.', ['evenement' => 'webhook.signature_rejetee']);

            return [Response::HTTP_UNAUTHORIZED, 'Signature invalide.'];
        }

        try {
            $evenement = $prestataire->analyserEvenement($corpsBrut);
        } catch (EvenementPaiementIllisible) {
            return [Response::HTTP_UNPROCESSABLE_ENTITY, 'Événement de paiement illisible.'];
        }

        // Seuls le type d'événement et l'identifiant de paiement sont journalisés.
        Log::info('webhook.evenement_recu : événement de paiement reçu.', [
            'evenement' => 'webhook.evenement_recu',
            'type' => $evenement->type ?? 'inconnu',
            'paiement' => $evenement->reference !== '' ? $evenement->reference : null,
        ]);

        if ($evenement->genre === GenreEvenement::Ignore) {
            return [Response::HTTP_OK, 'Événement ignoré.'];
        }

        if ($evenement->genre === GenreEvenement::Versement) {
            return $this->versement($evenement);
        }

        $paiement = Paiement::query()->where('reference_dexpay', $evenement->reference)->first();
        if ($paiement === null) {
            Log::warning('Événement de paiement ignoré : référence inconnue.');

            return [Response::HTTP_OK, 'Événement ignoré.'];
        }

        if ($evenement->genre === GenreEvenement::Remboursement) {
            return $this->rembourse($paiement);
        }

        if (! $this->montantEgal($paiement->montant_acompte, $evenement->montant)) {
            Log::warning('Événement de paiement refusé : montant incohérent.', ['paiement_id' => $paiement->id]);

            return [Response::HTTP_UNPROCESSABLE_ENTITY, 'Montant incohérent.'];
        }

        return $evenement->statut === StatutPaiement::Reussi
            ? $this->reussir($paiement)
            : $this->echouer($paiement);
    }

    /**
     * Résultat d'un versement (payout.completed, payout.failed, payout.cancelled). Le retrait est retrouvé par l'identifiant des
     * métadonnées, par la référence DexPay mémorisée, ou par notre référence déterministe. Rejeu : « Déjà traité », sans second recrédit.
     *
     * @return array{0: int, 1: string}
     */
    private function versement(EvenementPaiement $evenement): array
    {
        $retrait = $this->trouverRetrait($evenement);
        if ($retrait === null || $evenement->statutVersement === null) {
            Log::warning('Événement de versement ignoré : retrait inconnu.');

            return [Response::HTTP_OK, 'Événement ignoré.'];
        }

        $change = app(RetraitService::class)->appliquerResultat($retrait, $evenement->statutVersement, $evenement->reference !== '' ? $evenement->reference : null);

        return [Response::HTTP_OK, $change ? ($evenement->statutVersement === StatutRetrait::Reussi ? 'Retrait réussi enregistré.' : 'Échec du retrait enregistré.') : 'Déjà traité.'];
    }

    private function trouverRetrait(EvenementPaiement $evenement): ?Retrait
    {
        $id = $evenement->retraitId
            ?? ContexteVersement::retraitIdDe($evenement->referenceMarchande)
            ?? ContexteVersement::retraitIdDe($evenement->reference);
        if ($id !== null && Str::isUuid($id) && ($retrait = Retrait::query()->find($id)) !== null) {
            return $retrait;
        }

        $references = array_filter([$evenement->reference, $evenement->referenceMarchande], fn (?string $r) => $r !== null && $r !== '');

        return $references === [] ? null : Retrait::query()->whereIn('reference_dexpay', $references)->first();
    }

    /**
     * Remboursement signalé par le prestataire (checkout.refunded) : le paiement passe à REMBOURSE, une seule fois, jamais
     * depuis EN_ATTENTE (rien n'a été payé). Un paiement déjà remboursé ne change pas, le remboursement n'est jamais redemandé.
     *
     * @return array{0: int, 1: string}
     */
    private function rembourse(Paiement $paiement): array
    {
        if ($paiement->rembourserSiStatut(StatutPaiement::Reussi, StatutPaiement::Echoue)) {
            return [Response::HTTP_OK, 'Remboursement enregistré.'];
        }

        return [Response::HTTP_OK, 'Déjà traité.'];
    }

    /** Comparaison décimale exacte (« 4500 » = « 4500.00 »). Un montant mal formé est incohérent. */
    private function montantEgal(string $attendu, string $recu): bool
    {
        try {
            return CalculPaiement::soustraire($attendu, '0') === CalculPaiement::soustraire($recu, '0');
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** @return array{0: int, 1: string} */
    private function echouer(Paiement $paiement): array
    {
        // Un « échoué » arrivé dans le désordre ne touche jamais un paiement réussi, échoué ou remboursé.
        $modifie = $paiement->echouerSiEnAttente();

        return [Response::HTTP_OK, $modifie ? 'Échec du paiement enregistré.' : 'Déjà traité.'];
    }

    /** @return array{0: int, 1: string} */
    private function reussir(Paiement $paiement): array
    {
        $confirmee = null;
        $aRembourser = null;

        for ($essai = 1; $essai <= self::ESSAIS_CODE; $essai++) {
            try {
                [$etat, $reservation] = DB::transaction(fn () => $this->confirmer($paiement));
            } catch (UniqueConstraintViolationException) {
                // Collision du code de réservation : toute la transaction est annulée, on recommence avec un autre code.
                continue;
            }

            if ($etat === 'deja_traite') {
                return [Response::HTTP_OK, 'Déjà traité.'];
            }
            $confirmee = $etat === 'confirmee' ? $reservation : null;
            $aRembourser = $etat === 'confirmee' ? null : $etat;

            break;
        }

        if ($confirmee === null && $aRembourser === null) {
            Log::error('Confirmation de paiement impossible : collisions répétées du code de réservation.', ['paiement_id' => $paiement->id]);

            throw new RuntimeException('Impossible de générer un code de réservation unique.');
        }

        if ($confirmee !== null) {
            // Après validation de la transaction (l'événement attend aussi le commit s'il est publié dans une transaction).
            ReservationConfirmee::dispatch($confirmee);

            return [Response::HTTP_OK, 'Paiement confirmé.'];
        }

        // Argent reçu mais réservation plus confirmable : remboursement, APRÈS la transaction.
        $this->rembourserSansBloquer($paiement, $aRembourser);

        return [Response::HTTP_OK, 'Paiement reçu.'];
    }

    /**
     * Transaction : paiement vers REUSSI, puis réservation vers CONFIRMEE avec son code.
     *
     * @return array{0: string, 1: ?Reservation} état « confirmee », « reservation_non_confirmable », « paiement_en_trop » ou « deja_traite »
     */
    private function confirmer(Paiement $paiement): array
    {
        try {
            // Point de sauvegarde : un refus de l'index unique partiel ne casse pas la transaction (PostgreSQL).
            $active = DB::transaction(fn () => $paiement->refresh()->reussirSiEnAttenteOuEchoue());
        } catch (UniqueConstraintViolationException) {
            // Un autre paiement actif existe pour cette réservation : celui-ci est en trop et sera remboursé.
            // Marqué « dû » dans la transaction du webhook (M6.5), avant toute tentative, pour rester retrouvable.
            $this->remboursements->marquerDu($paiement->refresh());

            return ['paiement_en_trop', null];
        }

        if (! $active) {
            return ['deja_traite', null];
        }

        $reservation = Reservation::query()->findOrFail($paiement->reservation_id);
        if ($reservation->confirmerSiAcceptee($this->generateur->generer())) {
            return ['confirmee', $reservation];
        }

        // Argent reçu mais réservation plus confirmable : marqué « dû » dans la même transaction (M6.5).
        $this->remboursements->marquerDu($paiement);

        return ['reservation_non_confirmable', null];
    }

    /** Le webhook répond 200 même si le remboursement échoue (on ne veut pas de réémission en boucle). */
    private function rembourserSansBloquer(Paiement $paiement, string $etat): void
    {
        try {
            $depuis = $etat === 'paiement_en_trop' ? StatutPaiement::Echoue : StatutPaiement::Reussi;
            $this->remboursements->rembourser($paiement, $depuis);
        } catch (Throwable $e) {
            Log::error('Remboursement impossible après un paiement non confirmable.', ['paiement_id' => $paiement->id, 'erreur' => $e::class]);
        }
    }
}
