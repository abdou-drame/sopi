<?php

namespace Database\Factories;

use App\Enums\Metier;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'metier' => fake()->randomElement(Metier::cases()),
            'nom' => fake()->unique()->words(2, true),
        ];
    }

    public function metier(Metier $metier): static
    {
        return $this->state(['metier' => $metier]);
    }
}
