<?php

namespace App\Models;

use App\Enums\PlateformeAppareil;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Appareil d'un utilisateur : jeton FCM d'une installation de l'application. Le jeton est unique (un seul propriétaire) et
 * n'est jamais renvoyé par l'API. Géré uniquement par AppareilService (aucune affectation de masse).
 */
class Appareil extends Model
{
    use HasUuids;

    protected $table = 'appareils';

    protected $fillable = [];

    protected function casts(): array
    {
        return ['plateforme' => PlateformeAppareil::class, 'derniere_activite' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
