<?php

namespace App\Models;

use App\Enums\TypeSanction;
use Database\Factories\SanctionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sanction infligée à un utilisateur (diagramme de classes). L'historique est conservé : une sanction n'est jamais supprimée,
 * seulement levée. Créée et levée uniquement par SanctionService (aucune affectation de masse).
 */
class Sanction extends Model
{
    /** @use HasFactory<SanctionFactory> */
    use HasFactory, HasUuids;

    protected $table = 'sanctions';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type' => TypeSanction::class,
            'date_fin' => 'datetime',
            'levee_le' => 'datetime',
        ];
    }

    /** Sanctions non levées. */
    public function scopeActives(Builder $requete): Builder
    {
        return $requete->whereNull('levee_le');
    }

    /** Sanction qui bloque le compte (suspension ou bannissement) et n'est pas levée. */
    public function scopeBloquantesActives(Builder $requete): Builder
    {
        return $requete->whereNull('levee_le')->whereIn('type', [TypeSanction::SuspensionTemporaire->value, TypeSanction::BannissementDefinitif->value]);
    }

    /** Utilisateur sanctionné. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function litige(): BelongsTo
    {
        return $this->belongsTo(Litige::class);
    }

    public function avis(): BelongsTo
    {
        return $this->belongsTo(Avis::class);
    }

    /** Administrateur qui a posé la sanction. */
    public function posePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pose_par_id');
    }

    /** Administrateur qui l'a levée ; vide pour une levée automatique ou une sanction non levée. */
    public function leveePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'levee_par_id');
    }
}
