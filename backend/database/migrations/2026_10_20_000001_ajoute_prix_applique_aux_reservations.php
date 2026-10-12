<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attribut technique (absent du diagramme) : prix convenu, figé à la création de la réservation.
        // Ajouté nullable pour pouvoir remplir les réservations existantes, puis rendu obligatoire.
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('prix_applique', 12, 2)->nullable()->after('prestation_id');
        });

        DB::statement('UPDATE reservations SET prix_applique = (SELECT prestations.prix FROM prestations WHERE prestations.id = reservations.prestation_id)');

        $this->rendreObligatoire();
    }

    /**
     * Obligatoire et strictement positif, garanti par la base sur les deux moteurs. PostgreSQL : changement de colonne natif
     * et CHECK. SQLite : reconstruire la table (ce que fait change()) emporterait les clés étrangères de paiements et l'index
     * unique partiel des réservations ; on utilise donc des déclencheurs équivalents (même technique qu'en M6.1).
     */
    private function rendreObligatoire(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('reservations', function (Blueprint $table) {
                $table->decimal('prix_applique', 12, 2)->nullable(false)->change();
            });
            DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_prix_applique_positif CHECK (prix_applique > 0)');

            return;
        }

        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("CREATE TRIGGER reservations_prix_applique_{$nom} BEFORE {$evenement} ON reservations
                WHEN NEW.prix_applique IS NULL OR NEW.prix_applique <= 0
                BEGIN SELECT RAISE(ABORT, 'reservations_prix_applique'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_prix_applique_positif');
        } else {
            DB::statement('DROP TRIGGER IF EXISTS reservations_prix_applique_insertion');
            DB::statement('DROP TRIGGER IF EXISTS reservations_prix_applique_mise_a_jour');
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('prix_applique');
        });
    }
};
