<?php

namespace App\Models;

use App\Enums\DecisionLitige;
use App\Enums\StatutLitige;
use Database\Factories\LitigeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Litige signalé par un client sur une réservation (diagramme de classes) : motif, description, preuves, réponse du prestataire,
 * puis décision de l'administrateur qui le clôt. Créé et modifié uniquement par des services (aucune affectation de masse).
 */
class Litige extends Model
{
    /** @use HasFactory<LitigeFactory> */
    use HasFactory, HasUuids;

    protected $table = 'litiges';

    protected $fillable = [];

    protected $attributes = [
        'statut' => 'ouvert',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutLitige::class,
            'decision' => DecisionLitige::class,
            'reponse_le' => 'datetime',
            'cloture_le' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** Client qui a signalé le litige. */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** Prestataire visé par le litige. */
    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }

    /** Administrateur qui a rendu la décision. */
    public function decidePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par_id');
    }

    public function preuves(): HasMany
    {
        return $this->hasMany(LitigePreuve::class);
    }

    /** Sanction prononcée par la décision, s'il y en a une. */
    public function sanction(): HasOne
    {
        return $this->hasOne(Sanction::class);
    }
}
