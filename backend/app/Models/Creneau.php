<?php

namespace App\Models;

use App\Enums\StatutCreneau;
use Database\Factories\CreneauFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Créneau de disponibilité d'un prestataire (diagramme de classes).
 * Au plus une réservation active par créneau : règle gérée avec les réservations (M5).
 */
class Creneau extends Model
{
    /** @use HasFactory<CreneauFactory> */
    use HasFactory, HasUuids;

    protected $table = 'creneaux';

    // prestataire_id est posé par la relation ; le statut et le verrou changent par des méthodes métier (M4.5), jamais en masse.
    protected $fillable = [
        'date_debut',
        'date_fin',
    ];

    protected $attributes = [
        'statut' => 'libre',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
            'statut' => StatutCreneau::class,
            'verrou_expire_a' => 'datetime',
        ];
    }

    /** Même règle que la contrainte CHECK de PostgreSQL, sur tous les moteurs. */
    protected static function booted(): void
    {
        static::saving(function (Creneau $creneau) {
            if ($creneau->date_debut === null || $creneau->date_fin === null || $creneau->date_fin->lte($creneau->date_debut)) {
                throw new InvalidArgumentException('La fin d\'un créneau doit être postérieure à son début.');
            }
        });
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }

    /** Libre, ou verrouillé avec un verrou expiré (le verrou ne bloque plus personne). */
    public function estLibre(): bool
    {
        return match ($this->statut) {
            StatutCreneau::Libre => true,
            StatutCreneau::Verrouille => $this->verrou_expire_a !== null && $this->verrou_expire_a->lte(now()),
            default => false,
        };
    }

    /**
     * Verrouille le créneau pour un client, en UNE requête conditionnelle atomique (RG08, RG09) : libre, ou verrou expiré,
     * et début dans le futur. Vrai si exactement une ligne a changé. Aucune lecture avant l'écriture.
     */
    public function verrouillerSiLibre(int $dureeSec, string $clientId): bool
    {
        $maintenant = now();

        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('date_debut', '>', $maintenant)
            ->where(function (Builder $q) use ($maintenant) {
                $q->where('statut', StatutCreneau::Libre->value)
                    ->orWhere(fn (Builder $v) => $v->where('statut', StatutCreneau::Verrouille->value)
                        ->where('verrou_expire_a', '<=', $maintenant));
            })
            ->update([
                'statut' => StatutCreneau::Verrouille->value,
                'verrou_expire_a' => $maintenant->copy()->addSeconds($dureeSec),
                'verrouille_par' => $clientId,
            ]);

        if ($modifies === 1) {
            $this->refresh();
        }

        return $modifies === 1;
    }

    /** Remet le créneau à libre, uniquement s'il est verrouillé par ce client. Vrai si le verrou a été levé. */
    public function libererVerrou(string $clientId): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutCreneau::Verrouille->value)
            ->where('verrouille_par', $clientId)
            ->update(['statut' => StatutCreneau::Libre->value, 'verrou_expire_a' => null, 'verrouille_par' => null]) === 1;
    }

    /**
     * Libère en une seule requête tous les créneaux dont le verrou a expiré (RG09) : VERROUILLE vers LIBRE, échéance vidée.
     * Le dernier détenteur (verrouille_par) est conservé : tant qu'un autre client ne reprend pas le créneau, la demande de
     * réservation de ce client reçoit « Délai dépassé » (mémoire, tableau 2.6, scénario 3b) et non « créneau indisponible ».
     * Un verrou encore valide, un créneau réservé ou indisponible ne sont jamais touchés. Renvoie le nombre libéré.
     */
    public static function libererVerrousExpires(): int
    {
        return static::query()
            ->where('statut', StatutCreneau::Verrouille->value)
            ->where('verrou_expire_a', '<', now())
            ->update(['statut' => StatutCreneau::Libre->value, 'verrou_expire_a' => null]);
    }

    /**
     * Vrai si ce client tenait le verrou de ce créneau et qu'il a expiré : soit le verrou est encore posé mais périmé,
     * soit le balayage de la minute l'a déjà levé en gardant le client comme dernier détenteur. Faux dans tous les
     * autres cas (jamais verrouillé par lui, repris par un autre client, créneau réservé ou indisponible).
     */
    public function verrouExpireDuClient(string $clientId): bool
    {
        if ($this->verrouille_par !== $clientId) {
            return false;
        }

        return match ($this->statut) {
            StatutCreneau::Verrouille => $this->verrou_expire_a !== null && $this->verrou_expire_a->lte(now()),
            StatutCreneau::Libre => true,
            default => false,
        };
    }

    /**
     * Libère un créneau réservé quand sa réservation est refusée : RESERVE vers LIBRE, verrou vidé, et seulement s'il est
     * encore réservé ET lié à cette réservation. Vrai si le créneau a été libéré.
     */
    public function libererPourReservation(Reservation $reservation): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutCreneau::Reserve->value)
            ->whereHas('reservations', fn (Builder $q) => $q->whereKey($reservation->getKey()))
            ->update(['statut' => StatutCreneau::Libre->value, 'verrou_expire_a' => null, 'verrouille_par' => null]) === 1;
    }

    /** Passe un créneau RÉSERVÉ à indisponible (réservation annulée par indisponibilité, M5.7). Vrai si une ligne a changé. */
    public function bloquerSiReserve(): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutCreneau::Reserve->value)
            ->update(['statut' => StatutCreneau::Indisponible->value, 'verrou_expire_a' => null, 'verrouille_par' => null]) === 1;
    }

    /** Passe le créneau à réservé, uniquement s'il est verrouillé par ce client et que son verrou n'a pas expiré (M5). */
    public function marquerReserve(string $clientId): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutCreneau::Verrouille->value)
            ->where('verrouille_par', $clientId)
            ->where('verrou_expire_a', '>', now())
            ->update(['statut' => StatutCreneau::Reserve->value, 'verrou_expire_a' => null, 'verrouille_par' => null]) === 1;
    }

    /** Créneaux libres, au sens de estLibre(). */
    public function scopeLibres(Builder $requete): void
    {
        $requete->where(function (Builder $q) {
            $q->where('statut', StatutCreneau::Libre->value)
                ->orWhere(fn (Builder $v) => $v->where('statut', StatutCreneau::Verrouille->value)
                    ->where('verrou_expire_a', '<=', now()));
        });
    }

    /** Créneaux qui n'ont pas encore commencé. */
    public function scopeAVenir(Builder $requete): void
    {
        $requete->where('date_debut', '>', now());
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
