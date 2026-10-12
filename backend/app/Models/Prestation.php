<?php

namespace App\Models;

use App\Enums\Metier;
use Database\Factories\PrestationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Prestation proposée par un prestataire (diagramme de classes) : nom, domaine, durée estimée et prix en XOF.
 * Une prestation se désactive (actif), elle ne se supprime pas.
 */
class Prestation extends Model
{
    /** @use HasFactory<PrestationFactory> */
    use HasFactory, HasUuids;

    protected $table = 'prestations';

    // prestataire_id est posé par la relation (jamais depuis une requête).
    // Le domaine n'est pas assignable : il est toujours celui du prestataire propriétaire.
    protected $fillable = [
        'nom',
        'duree_minutes',
        'prix',
        'actif',
    ];

    protected $attributes = [
        'actif' => true,
    ];

    protected function casts(): array
    {
        return [
            'domaine' => Metier::class,
            'prix' => 'decimal:2',
            'duree_minutes' => 'integer',
            'actif' => 'boolean',
        ];
    }

    /** Mêmes règles que les contraintes CHECK de PostgreSQL, sur tous les moteurs. */
    protected static function booted(): void
    {
        static::saving(function (Prestation $prestation) {
            if ((float) $prestation->prix <= 0) {
                throw new InvalidArgumentException('Le prix d\'une prestation doit être supérieur à 0.');
            }
            if ((int) $prestation->duree_minutes <= 0) {
                throw new InvalidArgumentException('La durée d\'une prestation doit être supérieure à 0 minute.');
            }

            // Le domaine suit toujours le métier du prestataire : absent, il est repris ; différent, il est refusé.
            $metier = $prestation->prestataire?->metier;
            $domaine = $prestation->domaine;
            if ($domaine === null) {
                $prestation->domaine = $metier;
            } elseif ($metier !== null && $domaine !== $metier) {
                throw new InvalidArgumentException('Le domaine d\'une prestation doit être le métier de son prestataire.');
            }
        });
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
