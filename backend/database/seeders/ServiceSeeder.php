<?php

namespace Database\Seeders;

use App\Enums\Metier;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Référentiel de départ des services (CLAUDE.md, « Données de départ »). Idempotent : relançable sans doublon.
 */
class ServiceSeeder extends Seeder
{
    public const SERVICES = [
        'mecanique' => ['Vidange', 'Diagnostic', 'Freinage', 'Pneumatiques'],
        'coiffure' => ['Coupe homme', 'Coupe femme', 'Tresses', 'Coloration'],
        'pressing' => ['Chemise', 'Nettoyage à sec', 'Repassage', 'Couette'],
    ];

    public function run(): void
    {
        foreach (self::SERVICES as $metier => $noms) {
            foreach ($noms as $nom) {
                Service::firstOrCreate(['metier' => Metier::from($metier), 'nom' => $nom]);
            }
        }
    }
}
