<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Métier proposé sur Sopi (Mécanique, Coiffure, Pressing au départ), géré par l'administrateur. Un métier n'est jamais supprimé :
 * il est désactivé. Son code (minuscules, sans accent) est la valeur stockée chez les prestataires ; il est fixé à la création.
 * Créé et modifié uniquement par MetierService (aucune affectation de masse).
 */
class Metier extends Model
{
    use HasUuids;

    protected $table = 'metiers';

    protected $fillable = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function scopeActifs(Builder $requete): Builder
    {
        return $requete->where('actif', true);
    }
}
