<?php

namespace Database\Seeders;

use App\Models\Quartier;
use Illuminate\Database\Seeder;

class QuartierSeeder extends Seeder
{
    /** Idempotent : un nom déjà présent (casse ignorée) n'est pas recréé. */
    public function run(): void
    {
        foreach (Quartier::NOMS_DE_DEPART as $nom) {
            if (! Quartier::query()->whereRaw('lower(nom) = ?', [mb_strtolower($nom)])->exists()) {
                Quartier::create(['nom' => $nom]);
            }
        }
    }
}
