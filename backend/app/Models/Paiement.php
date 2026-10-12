<?php

namespace App\Models;

use App\Enums\StatutPaiement;
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

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
