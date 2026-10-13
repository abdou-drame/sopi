<?php

namespace App\Models;

use App\Enums\Metier;
use Database\Factories\PrestataireFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Profil professionnel d'un utilisateur de rôle prestataire (1-1 avec User).
 * Porte le solde (crédité à la fin des prestations, voir CLAUDE.md).
 */
class Prestataire extends Model
{
    /** @use HasFactory<PrestataireFactory> */
    use HasFactory, HasUuids;

    protected $table = 'prestataires';

    // Le solde et le hachage du lien de suivi ne sont volontairement PAS assignables en masse.
    protected $fillable = [
        'metier',
        'quartier',
        'nom_entreprise',
        'adresse',
        'photos_atelier',
    ];

    // Valeur par défaut côté modèle (la base a la même).
    protected $attributes = [
        'photos_atelier' => '[]',
        'solde' => '0.00',
    ];

    protected $hidden = [
        'jeton_suivi_hash',
    ];

    protected function casts(): array
    {
        return [
            'metier' => Metier::class,
            'photos_atelier' => 'array',
            'solde' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function justificatifs(): HasMany
    {
        return $this->hasMany(Justificatif::class);
    }

    public function prestations(): HasMany
    {
        return $this->hasMany(Prestation::class);
    }

    /** Avis reçus des clients. */
    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class);
    }

    public function retraits(): HasMany
    {
        return $this->hasMany(Retrait::class);
    }

    public function creneaux(): HasMany
    {
        return $this->hasMany(Creneau::class);
    }
}
