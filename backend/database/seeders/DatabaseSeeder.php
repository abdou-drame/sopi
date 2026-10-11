<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de départ, toutes idempotentes. L'administrateur n'est volontairement pas créé ici
     * (il a sa propre commande dédiée).
     */
    public function run(): void
    {
        $this->call(ServiceSeeder::class);
    }
}
