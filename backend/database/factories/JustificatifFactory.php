<?php

namespace Database\Factories;

use App\Enums\StatutJustificatif;
use App\Enums\TypeDocument;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Justificatif>
 */
class JustificatifFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prestataire_id' => Prestataire::factory(),
            'type_document' => fake()->randomElement(TypeDocument::cases()),
            'chemin_fichier' => 'justificatifs/'.fake()->uuid().'.pdf',
            'date_soumission' => now(),
        ];
    }

    public function valide(?User $administrateur = null): static
    {
        return $this->state(fn () => [
            'statut' => StatutJustificatif::Valide,
            'verifie_par' => ($administrateur ?? User::factory()->administrateur()->create())->id,
        ]);
    }

    public function rejete(string $motif = 'Document illisible.', ?User $administrateur = null): static
    {
        return $this->state(fn () => [
            'statut' => StatutJustificatif::Rejete,
            'motif_rejet' => $motif,
            'verifie_par' => ($administrateur ?? User::factory()->administrateur()->create())->id,
        ]);
    }
}
