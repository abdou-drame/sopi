<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\StatutCompte;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'telephone' => '+22177'.fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('motdepasse'),
            'role' => Role::Client,
            'statut' => StatutCompte::Actif,
        ];
    }

    public function sansEmail(): static
    {
        return $this->state(['email' => null]);
    }

    public function prestataire(): static
    {
        return $this->state(['role' => Role::Prestataire]);
    }

    public function administrateur(): static
    {
        return $this->state(['role' => Role::Administrateur]);
    }

    public function statut(StatutCompte $statut): static
    {
        return $this->state(['statut' => $statut]);
    }

    public function suspendu(): static
    {
        return $this->statut(StatutCompte::Suspendu);
    }
}
