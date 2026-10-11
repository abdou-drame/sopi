<?php

namespace App\Models;

use Database\Factories\PrestationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Service tel qu'un prestataire le propose (classe « PrestationPrestataire ») : tarif en XOF et durée estimée.
 */
class Prestation extends Model
{
    /** @use HasFactory<PrestationFactory> */
    use HasFactory, HasUuids;

    protected $table = 'prestations';

    // prestataire_id est posé par la relation (jamais depuis une requête).
    protected $fillable = [
        'service_id',
        'tarif',
        'duree_minutes',
        'description',
        'actif',
    ];

    protected $attributes = [
        'actif' => true,
    ];

    protected function casts(): array
    {
        return [
            'tarif' => 'decimal:2',
            'duree_minutes' => 'integer',
            'actif' => 'boolean',
        ];
    }

    /** Mêmes règles que les contraintes CHECK de PostgreSQL, sur tous les moteurs. */
    protected static function booted(): void
    {
        static::saving(function (Prestation $prestation) {
            if ((float) $prestation->tarif <= 0) {
                throw new InvalidArgumentException('Le tarif d\'une prestation doit être supérieur à 0.');
            }
            if ((int) $prestation->duree_minutes <= 0) {
                throw new InvalidArgumentException('La durée d\'une prestation doit être supérieure à 0 minute.');
            }
        });
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
