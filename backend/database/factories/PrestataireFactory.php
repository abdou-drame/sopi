<?php

namespace Database\Factories;

use App\Enums\StatutCompte;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prestataire>
 */
class PrestataireFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->prestataire(),
            'metier' => fake()->randomElement(['mecanique', 'coiffure', 'pressing']),
            'quartier' => fake()->randomElement(['Keur Massar Nord', 'Keur Massar Sud', 'Malika', 'Jaxaay']),
            'nom_entreprise' => fake()->company(),
            'adresse' => fake()->streetAddress(),
        ];
    }

    /** Solde de gains disponible (FCFA). Le solde n'est pas assignable en masse : posé directement pour les tests. */
    public function avoirSolde(string|int|float $montant): static
    {
        return $this->afterMaking(fn (Prestataire $prestataire) => $prestataire->solde = number_format((float) $montant, 2, '.', ''));
    }

    public function metier(string $metier): static
    {
        return $this->state(['metier' => $metier]);
    }

    /** Dossier refusé par un administrateur, avec son motif. */
    public function refuse(string $motif = 'Pièces illisibles.'): static
    {
        return $this->state([
            'user_id' => User::factory()->prestataire()->statut(StatutCompte::Refuse),
            'motif_refus' => $motif,
        ]);
    }
}
