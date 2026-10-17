<?php

namespace Database\Factories;

use App\Models\Quartier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quartier>
 */
class QuartierFactory extends Factory
{
    public function definition(): array
    {
        return ['nom' => 'Quartier '.fake()->unique()->numerify('######')];
    }
}
