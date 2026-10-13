<?php

namespace Database\Factories;

use App\Enums\StatutRetrait;
use App\Models\Prestataire;
use App\Models\Retrait;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Par défaut : EN_COURS, 5 000 FCFA.
 *
 * @extends Factory<Retrait>
 */
class RetraitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prestataire_id' => Prestataire::factory(),
            'montant' => '5000.00',
            'statut' => StatutRetrait::EnCours,
            'telephone_versement' => '+22177'.fake()->numerify('#######'),
            'reference_dexpay' => null,
            'traite_le' => null,
            'recredite_le' => null,
        ];
    }

    public function enCours(): static
    {
        return $this->state(['statut' => StatutRetrait::EnCours]);
    }

    public function reussi(): static
    {
        return $this->state(fn () => ['statut' => StatutRetrait::Reussi, 'reference_dexpay' => $this->reference(), 'traite_le' => now()]);
    }

    public function echoue(): static
    {
        return $this->state(fn () => ['statut' => StatutRetrait::Echoue, 'reference_dexpay' => $this->reference(), 'traite_le' => now()]);
    }

    private function reference(): string
    {
        return 'DXP-RET-'.Str::upper(Str::random(12));
    }
}
