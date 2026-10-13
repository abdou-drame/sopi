<?php

namespace Database\Factories;

use App\Models\Litige;
use App\Models\LitigePreuve;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Preuve photographique factice : seulement le nom du fichier, aucun fichier n'est écrit.
 *
 * @extends Factory<LitigePreuve>
 */
class LitigePreuveFactory extends Factory
{
    public function definition(): array
    {
        return [
            'litige_id' => Litige::factory(),
            'fichier' => Str::uuid().'.jpg',
        ];
    }
}
