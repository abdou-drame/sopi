<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\StatutCompte;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
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

    public function estActif(): bool
    {
        return $this->statut === StatutCompte::Actif;
    }
}
