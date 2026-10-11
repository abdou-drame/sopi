<?php

namespace Database\Factories;

use App\Enums\Metier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prestataire>
 */
class PrestataireFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->prestataire(),
            'metier' => fake()->randomElement(Metier::cases()),
            'quartier' => fake()->randomElement(['Keur Massar Nord', 'Keur Massar Sud', 'Malika', 'Jaxaay']),
            'nom_entreprise' => fake()->company(),
            'adresse' => fake()->streetAddress(),
        ];
    }

    public function metier(Metier $metier): static
    {
        return $this->state(['metier' => $metier]);
    }
}
