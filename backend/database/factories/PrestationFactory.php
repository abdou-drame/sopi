<?php

namespace Database\Factories;

use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Service;
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
            'service_id' => Service::factory(),
            'tarif' => fake()->numberBetween(5, 100) * 500,
            'duree_minutes' => fake()->randomElement([15, 30, 45, 60, 90, 120]),
            'description' => fake()->sentence(),
            'actif' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['actif' => false]);
    }
}
