<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeton de réinitialisation du mot de passe : seul le hachage SHA-256 est en base. Géré uniquement par
 * ReinitialisationMotDePasseService (aucune affectation de masse, jamais exposé par l'API).
 */
class ReinitialisationMotDePasse extends Model
{
    use HasUuids;

    protected $table = 'reinitialisations_mot_de_passe';

    protected $fillable = [];

    protected $hidden = ['jeton_hash'];

    protected function casts(): array
    {
        return ['expire_le' => 'datetime', 'utilise_le' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
