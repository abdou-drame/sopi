<?php

namespace App\Models;

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use Carbon\CarbonInterface;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Réservation d'un créneau par un client pour une prestation (diagramme de classes).
 * Au plus une réservation active par créneau (index unique partiel). Les transitions de statut viennent en M5.2+.
 */
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory, HasUuids;

    protected $table = 'reservations';

    // Clés étrangères, prix_applique (prix convenu, figé à la création), statut, motif, code et acceptee_le sont posés par le métier, jamais en masse.
    protected $fillable = [];

    protected $attributes = [
        'statut' => 'en_attente',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutReservation::class,
            'prix_applique' => 'decimal:2',
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

    /**
     * Annule une réservation active mais pas encore commencée (EN_ATTENTE, ACCEPTEE ou CONFIRMEE) parce que le prestataire
     * a déclaré le créneau indisponible (M5.7). Une seule mise à jour conditionnelle. Vrai si exactement une ligne a changé.
     *
     * Le marquage « remboursement dû » du paiement est posé par l'appelant, dans la même transaction (M6.5).
     */
    public function annulerPourIndisponibilite(): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->whereIn('statut', array_column(StatutReservation::ANNULABLES_PAR_CLIENT, 'value'))
            ->update(['statut' => StatutReservation::Annulee->value]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /**
     * Annule une réservation ACCEPTEE ou CONFIRMEE à la demande du prestataire propriétaire du créneau, avec son motif
     * (stocké dans motif_refus), seulement si le créneau n'a pas commencé (M6.5). Une seule mise à jour conditionnelle.
     * Vrai si exactement une ligne a changé.
     */
    public function annulerParPrestataireSiPossible(string $prestataireId, string $motif): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->whereIn('statut', [StatutReservation::Acceptee->value, StatutReservation::Confirmee->value])
            ->whereHas('creneau', fn (Builder $q) => $q->where('prestataire_id', $prestataireId)->where('date_debut', '>', now()))
            ->update(['statut' => StatutReservation::Annulee->value, 'motif_refus' => $motif, 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /**
     * Prend le verrou de ligne pour initier le paiement (M6.3) sans rien changer d'utile : « touche » updated_at, seulement si la
     * réservation est au client, toujours ACCEPTEE et dans le délai de paiement ($limite = maintenant moins le délai).
     * Pendant la transaction, l'expiration et l'annulation ne peuvent plus passer. Vrai si exactement une ligne a changé.
     */
    public function prendreVerrouPaiement(string $clientId, CarbonInterface $limite): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->where('client_id', $clientId)
            ->where('statut', StatutReservation::Acceptee->value)
            ->where('acceptee_le', '>', $limite)
            ->update(['updated_at' => now()]) === 1;
    }

    /**
     * Passe ACCEPTEE à CONFIRMEE avec son code, en UNE requête conditionnelle (webhook de paiement, M6.4). Le délai de
     * paiement n'est volontairement pas revérifié : seule l'annulation par la tâche planifiée ferme la porte.
     * Peut lever UniqueConstraintViolationException si le code existe déjà. Vrai si exactement une ligne a changé.
     */
    public function confirmerSiAcceptee(string $code): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutReservation::Acceptee->value)
            ->update(['statut' => StatutReservation::Confirmee->value, 'code_reservation' => $code, 'updated_at' => now()]);

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

    /** Avis du client sur cette réservation (au plus un). */
    public function avis(): HasOne
    {
        return $this->hasOne(Avis::class);
    }

    /** Litiges signalés sur cette réservation (au plus un, ouvert ou clôturé). */
    public function litiges(): HasMany
    {
        return $this->hasMany(Litige::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    /** Paiement en cours ou réussi (lecture seule) ; null si aucun. Au plus un, garanti par la base. */
    public function paiementActif(): HasOne
    {
        return $this->hasOne(Paiement::class)->whereIn('statut', array_column(StatutPaiement::ACTIFS, 'value'));
    }
}
