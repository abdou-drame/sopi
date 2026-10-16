<?php

namespace Database\Factories;

use App\Enums\TypeMouvementPortefeuille;
use App\Models\MouvementPortefeuille;
use App\Models\Prestataire;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MouvementPortefeuille> */
class MouvementPortefeuilleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prestataire_id' => Prestataire::factory(),
            'type' => TypeMouvementPortefeuille::Credit,
            'montant' => '3000.00',
            'libelle' => 'Prestation terminée',
            'solde_apres' => '3000.00',
        ];
    }
}
