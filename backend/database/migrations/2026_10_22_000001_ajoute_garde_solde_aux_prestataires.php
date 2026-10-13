<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // La colonne solde existe déjà (decimal(12,2) NOT NULL défaut 0, création de la table). On ajoute seulement la
        // garde « solde >= 0 », sans reconstruire la table prestataires (nombreuses clés étrangères).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE prestataires ADD CONSTRAINT prestataires_solde_positif CHECK (solde >= 0)');

            return;
        }

        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("CREATE TRIGGER prestataires_solde_positif_{$nom} BEFORE {$evenement} ON prestataires
                WHEN NOT (NEW.solde >= 0)
                BEGIN SELECT RAISE(ABORT, 'prestataires_solde_positif'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE prestataires DROP CONSTRAINT IF EXISTS prestataires_solde_positif');

            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS prestataires_solde_positif_insertion');
        DB::statement('DROP TRIGGER IF EXISTS prestataires_solde_positif_mise_a_jour');
    }
};
