<?php

namespace App\Models;

use App\Enums\StatutRetrait;
use Database\Factories\RetraitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retrait des gains d'un prestataire vers son compte mobile money (diagramme de classes). Le statut et les montants
 * changent par des méthodes métier de service (M7.4) : aucune affectation de masse.
 */
class Retrait extends Model
{
    /** @use HasFactory<RetraitFactory> */
    use HasFactory, HasUuids;

    protected $table = 'retraits';

    protected $fillable = [];

    protected $attributes = [
        'statut' => 'en_cours',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutRetrait::class,
            'montant' => 'decimal:2',
            'traite_le' => 'datetime',
            'recredite_le' => 'datetime',
        ];
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }
}
