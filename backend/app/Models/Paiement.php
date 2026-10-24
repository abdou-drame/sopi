<?php

namespace App\Models;

use App\Enums\StatutPaiement;
use App\Services\Paiement\MontantsPaiement;
use App\Services\Paiement\SessionPaiement;
use Carbon\CarbonInterface;
use Database\Factories\PaiementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Paiement de l'acompte d'une réservation (diagramme de classes). Les montants sont calculés par le serveur et le statut
 * change par des méthodes métier (M6.2+) : aucune affectation de masse. Au plus un paiement actif par réservation.
 */
class Paiement extends Model
{
    /** @use HasFactory<PaiementFactory> */
    use HasFactory, HasUuids;

    protected $table = 'paiements';

    protected $fillable = [];

    protected $attributes = [
        'statut' => 'en_attente',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutPaiement::class,
            'montant_acompte' => 'decimal:2',
            'commission' => 'decimal:2',
            'paye_le' => 'datetime',
            'rembourse_le' => 'datetime',
            'remboursement_du_le' => 'datetime',
            'credite_le' => 'datetime',
        ];
    }

    /** Crée le paiement EN_ATTENTE d'une réservation avec des montants calculés par le serveur (jamais en masse). */
    public static function ouvrir(string $reservationId, MontantsPaiement $montants, ?string $operateurSouhaite = null): self
    {
        $paiement = new self;
        $paiement->operateur_souhaite = $operateurSouhaite;
        $paiement->reservation_id = $reservationId;
        $paiement->montant_acompte = $montants->montantAcompte;
        $paiement->commission = $montants->commission;
        $paiement->statut = StatutPaiement::EnAttente;
        $paiement->save();

        return $paiement;
    }

    /**
     * Enregistre la session de paiement, uniquement si le paiement est toujours EN_ATTENTE et sans référence.
     * Une seule mise à jour conditionnelle. Vrai si exactement une ligne a changé.
     */
    public function enregistrerSessionSiEnAttente(SessionPaiement $session): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutPaiement::EnAttente->value)
            ->whereNull('reference_dexpay')
            ->update(['reference_dexpay' => $session->reference, 'url_paiement' => $session->urlPaiement, 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /**
     * Passe EN_ATTENTE à ECHOUE un paiement qui n'a jamais reçu de référence (la session n'a pas pu être créée),
     * éventuellement seulement s'il a été créé avant $creeAvant. Vrai si exactement une ligne a changé.
     */
    public function echouerSiSansReference(?CarbonInterface $creeAvant = null): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutPaiement::EnAttente->value)
            ->whereNull('reference_dexpay')
            ->when($creeAvant, fn ($q) => $q->where('created_at', '<=', $creeAvant))
            ->update(['statut' => StatutPaiement::Echoue->value, 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /**
     * Passe EN_ATTENTE ou ECHOUE (session orpheline payée après abandon) à REUSSI, avec paye_le. Une seule mise à jour
     * conditionnelle. Peut lever UniqueConstraintViolationException si un autre paiement actif existe pour la réservation.
     */
    public function reussirSiEnAttenteOuEchoue(): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->whereIn('statut', [StatutPaiement::EnAttente->value, StatutPaiement::Echoue->value])
            ->update(['statut' => StatutPaiement::Reussi->value, 'paye_le' => now(), 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /**
     * Passe à ECHOUE les paiements EN_ATTENTE d'une réservation dont le délai de paiement est dépassé : le paiement est
     * abandonné (mémoire, tableau 2.7, 3a : « refusé, échoué ou abandonné » est enregistré « Échoué »). Un paiement réussi ou
     * remboursé n'est jamais touché. Une session encore ouverte chez le prestataire de paiement et réglée plus tard reste
     * traitée par le webhook (REUSSI puis remboursement). Une seule mise à jour conditionnelle ; renvoie le nombre de lignes changées.
     */
    public static function abandonnerLesEnAttenteDe(string $reservationId): int
    {
        return static::query()
            ->where('reservation_id', $reservationId)
            ->where('statut', StatutPaiement::EnAttente->value)
            ->update(['statut' => StatutPaiement::Echoue->value, 'updated_at' => now()]);
    }

    /** Passe EN_ATTENTE à ECHOUE (événement « échoué » du webhook) ; jamais un paiement réussi ou remboursé. */
    public function echouerSiEnAttente(): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutPaiement::EnAttente->value)
            ->update(['statut' => StatutPaiement::Echoue->value, 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /**
     * Pose la garde « solde crédité » (M7.2), une seule fois : seulement si le paiement est REUSSI et pas déjà crédité.
     * Vrai si exactement une ligne a changé (l'appelant crédite alors le solde, dans la même transaction).
     */
    public function crediterSiPossible(): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutPaiement::Reussi->value)
            ->whereNull('credite_le')
            ->update(['credite_le' => now(), 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /** Passe à REMBOURSE, seulement si le statut actuel est l'un de $depuis. Vrai si exactement une ligne a changé. */
    public function rembourserSiStatut(StatutPaiement ...$depuis): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->whereIn('statut', array_map(fn (StatutPaiement $s) => $s->value, $depuis))
            ->update(['statut' => StatutPaiement::Rembourse->value, 'rembourse_le' => now(), 'remboursement_derniere_erreur' => null, 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /** Garde la cause (code HTTP + extrait masqué, déjà bornés) du dernier remboursement échoué, pour l'administrateur. */
    public function noterErreurRemboursement(string $erreur): void
    {
        static::query()->whereKey($this->getKey())->update(['remboursement_derniere_erreur' => $erreur, 'updated_at' => now()]);
    }

    /**
     * Marque le paiement « à rembourser » (M6.5), une seule fois : seulement s'il est REUSSI ou ECHOUE (argent reçu) et pas
     * déjà marqué. Utilisable dans une transaction. Vrai si exactement une ligne a changé.
     */
    public function marquerRemboursementDuSiPossible(): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->whereIn('statut', [StatutPaiement::Reussi->value, StatutPaiement::Echoue->value])
            ->whereNull('remboursement_du_le')
            ->update(['remboursement_du_le' => now(), 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /** Compte un essai de reprise, seulement si le paiement est toujours dû, non remboursé et sous le maximum d'essais. */
    public function compterTentativeRemboursement(int $maximum): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->whereIn('statut', [StatutPaiement::Reussi->value, StatutPaiement::Echoue->value])
            ->whereNotNull('remboursement_du_le')
            ->where('remboursement_tentatives', '<', $maximum)
            ->update(['remboursement_tentatives' => DB::raw('remboursement_tentatives + 1'), 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
