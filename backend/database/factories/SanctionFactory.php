<?php

namespace Database\Factories;

use App\Enums\TypeSanction;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Par défaut : avertissement posé par un administrateur sur un prestataire, non levé.
 *
 * @extends Factory<Sanction>
 */
class SanctionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->prestataire(),
            'type' => TypeSanction::Avertissement,
            'motif' => 'Comportement contraire aux règles de la plateforme.',
            'date_fin' => null,
            'pose_par_id' => User::factory()->administrateur(),
        ];
    }

    public function suspension(?\DateTimeInterface $dateFin = null): static
    {
        return $this->state(fn () => ['type' => TypeSanction::SuspensionTemporaire, 'date_fin' => $dateFin ?? now()->addDays(7)]);
    }

    public function bannissement(): static
    {
        return $this->state(['type' => TypeSanction::BannissementDefinitif, 'date_fin' => null]);
    }

    /** Sanction levée par un administrateur. */
    public function levee(): static
    {
        return $this->state(fn () => ['levee_le' => now(), 'levee_par_id' => User::factory()->administrateur()]);
    }
}
