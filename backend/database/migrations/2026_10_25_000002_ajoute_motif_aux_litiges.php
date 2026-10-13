<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attribut « motif » de la classe Litige (diagramme de classes) : raison courte donnée à l'ouverture, distincte de la
        // description détaillée et du motif de la décision de l'administrateur (motif_decision).
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('litiges', function (Blueprint $table) {
                $table->string('motif', 150)->nullable();
            });
            // Litiges existants : le motif reprend le début de la description (tronqué à 150 caractères).
            DB::statement('UPDATE litiges SET motif = substr(description, 1, 150)');
            DB::statement('ALTER TABLE litiges ALTER COLUMN motif SET NOT NULL');

            return;
        }

        // SQLite ne sait pas passer une colonne à NOT NULL sans reconstruire la table (et perdre ses déclencheurs et index
        // partiels) : la colonne est créée NOT NULL avec une valeur par défaut vide, aussitôt remplacée pour les lignes existantes.
        // L'application fournit toujours un motif (3 à 150 caractères).
        DB::statement("ALTER TABLE litiges ADD COLUMN motif varchar(150) NOT NULL DEFAULT ''");
        DB::statement('UPDATE litiges SET motif = substr(description, 1, 150)');
    }

    public function down(): void
    {
        Schema::table('litiges', function (Blueprint $table) {
            $table->dropColumn('motif');
        });
    }
};
