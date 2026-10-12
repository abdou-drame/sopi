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
        ];
    }

    /** Crée le paiement EN_ATTENTE d'une réservation avec des montants calculés par le serveur (jamais en masse). */
    public static function ouvrir(string $reservationId, MontantsPaiement $montants): self
    {
        $paiement = new self;
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

    /** Passe EN_ATTENTE à ECHOUE (événement « échoué » du webhook) ; jamais un paiement réussi ou remboursé. */
    public function echouerSiEnAttente(): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutPaiement::EnAttente->value)
            ->update(['statut' => StatutPaiement::Echoue->value, 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /** Passe à REMBOURSE, seulement si le statut actuel est l'un de $depuis. Vrai si exactement une ligne a changé. */
    public function rembourserSiStatut(StatutPaiement ...$depuis): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->whereIn('statut', array_map(fn (StatutPaiement $s) => $s->value, $depuis))
            ->update(['statut' => StatutPaiement::Rembourse->value, 'rembourse_le' => now(), 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
