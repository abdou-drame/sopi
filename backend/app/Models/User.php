<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\StatutCompte;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids;

    // Le rôle et le statut ne sont volontairement PAS assignables en masse.
    protected $fillable = [
        'nom',
        'prenom',
        'telephone',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'statut' => StatutCompte::class,
        ];
    }

    /** Profil professionnel (uniquement pour les utilisateurs de rôle prestataire). */
    public function prestataire(): HasOne
    {
        return $this->hasOne(Prestataire::class);
    }

    public function estActif(): bool
    {
        return $this->statut === StatutCompte::Actif;
    }

    /** Réservations faites en tant que client. */
    public function appareils(): HasMany
    {
        return $this->hasMany(Appareil::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'client_id');
    }

    /** Litiges signalés en tant que client. */
    public function litiges(): HasMany
    {
        return $this->hasMany(Litige::class, 'client_id');
    }

    /** Historique des sanctions reçues (levées ou non). */
    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class);
    }

    /** Avis donnés en tant que client. */
    public function avisDonnes(): HasMany
    {
        return $this->hasMany(Avis::class, 'client_id');
    }
}
