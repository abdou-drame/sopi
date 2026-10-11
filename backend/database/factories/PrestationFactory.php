<?php

namespace Database\Factories;

use App\Models\Prestataire;
use App\Models\Prestation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prestation>
 */
class PrestationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prestataire_id' => Prestataire::factory(),
            'nom' => fake()->unique()->words(3, true),
            // Le domaine est celui du prestataire propriétaire.
            'domaine' => fn (array $attributs) => Prestataire::findOrFail($attributs['prestataire_id'])->metier,
            'duree_minutes' => fake()->randomElement([15, 30, 45, 60, 90, 120]),
            'prix' => fake()->numberBetween(5, 100) * 500,
            'actif' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['actif' => false]);
    }
}
