<?php

namespace App\Models;

use App\Enums\StatutReservation;
use Carbon\CarbonInterface;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réservation d'un créneau par un client pour une prestation (diagramme de classes).
 * Au plus une réservation active par créneau (index unique partiel). Les transitions de statut viennent en M5.2+.
 */
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory, HasUuids;

    protected $table = 'reservations';

    // Clés étrangères, statut, motif, code et acceptee_le sont posés par le métier, jamais en masse.
    protected $fillable = [];

    protected $attributes = [
        'statut' => 'en_attente',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutReservation::class,
            'acceptee_le' => 'datetime',
        ];
    }

    /**
     * Passe EN_ATTENTE à ACCEPTEE (acceptee_le = maintenant), en UNE requête conditionnelle : seulement si la demande est
     * toujours en attente et créée après $limite (délai de réponse). Vrai si exactement une ligne a changé.
     */
    public function accepterSiEnAttente(CarbonInterface $limite): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutReservation::EnAttente->value)
            ->where('created_at', '>', $limite)
            ->update(['statut' => StatutReservation::Acceptee->value, 'acceptee_le' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /** Passe EN_ATTENTE à REFUSEE avec son motif, dans les mêmes conditions que accepterSiEnAttente(). */
    public function refuserSiEnAttente(string $motif, CarbonInterface $limite): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutReservation::EnAttente->value)
            ->where('created_at', '>', $limite)
            ->update(['statut' => StatutReservation::Refusee->value, 'motif_refus' => $motif]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /**
     * Annule la réservation du client si elle est encore annulable et si le créneau commence au plus tôt à $limite
     * (UTC). Une seule mise à jour conditionnelle, sans lecture préalable. Vrai si exactement une ligne a changé.
     */
    public function annulerParClientSiPossible(string $clientId, CarbonInterface $limite): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('client_id', $clientId)
            ->whereIn('statut', array_column(StatutReservation::ANNULABLES_PAR_CLIENT, 'value'))
            ->whereHas('creneau', fn (Builder $q) => $q->where('date_debut', '>=', $limite))
            ->update(['statut' => StatutReservation::Annulee->value]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /**
     * Passe CONFIRMEE à EN_COURS si le code correspond, pour une réservation d'un créneau de ce prestataire.
     * Une seule mise à jour conditionnelle. Vrai si exactement une ligne a changé.
     */
    public function demarrerSiCodeValide(string $code, string $prestataireId): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutReservation::Confirmee->value)
            ->where('code_reservation', $code)
            ->whereHas('creneau', fn (Builder $q) => $q->where('prestataire_id', $prestataireId))
            ->update(['statut' => StatutReservation::EnCours->value]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /** Passe EN_COURS à TERMINEE pour une réservation d'un créneau de ce prestataire. Vrai si une ligne a changé. */
    public function terminerSiEnCours(string $prestataireId): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutReservation::EnCours->value)
            ->whereHas('creneau', fn (Builder $q) => $q->where('prestataire_id', $prestataireId))
            ->update(['statut' => StatutReservation::Terminee->value]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /**
     * Annule une demande restée EN_ATTENTE depuis $limite ou avant (RG10). Une seule mise à jour conditionnelle :
     * si le prestataire a répondu entre-temps, rien ne change. Vrai si exactement une ligne a changé.
     */
    public function expirerSiSansReponse(CarbonInterface $limite): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutReservation::EnAttente->value)
            ->where('created_at', '<=', $limite)
            ->update(['statut' => StatutReservation::Annulee->value]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /** Annule une réservation ACCEPTEE non payée depuis $limite ou avant, comptée depuis acceptee_le (RG11). */
    public function expirerSiImpayee(CarbonInterface $limite): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutReservation::Acceptee->value)
            ->where('acceptee_le', '<=', $limite)
            ->update(['statut' => StatutReservation::Annulee->value]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creneau(): BelongsTo
    {
        return $this->belongsTo(Creneau::class);
    }

    public function prestation(): BelongsTo
    {
        return $this->belongsTo(Prestation::class);
    }
}
