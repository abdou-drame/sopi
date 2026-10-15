<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opérateur mobile money du versement (enum OperateurRetrait). Nullable : les retraits existants n'en ont pas.
        // reference_dexpay existe déjà (création de la table) et son unicité fournit déjà l'index : rien à ajouter.
        Schema::table('retraits', function (Blueprint $table) {
            $table->string('operateur', 20)->nullable()->after('telephone_versement');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE retraits ADD CONSTRAINT retraits_operateur_valide CHECK (operateur IS NULL OR operateur IN ('wave', 'orange_money'))");

            return;
        }

        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("CREATE TRIGGER retraits_operateur_valide_{$nom} BEFORE {$evenement} ON retraits
                WHEN NOT (NEW.operateur IS NULL OR NEW.operateur IN ('wave', 'orange_money'))
                BEGIN SELECT RAISE(ABORT, 'retraits_operateur_valide'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE retraits DROP CONSTRAINT IF EXISTS retraits_operateur_valide');
        } else {
            DB::statement('DROP TRIGGER IF EXISTS retraits_operateur_valide_insertion');
            DB::statement('DROP TRIGGER IF EXISTS retraits_operateur_valide_mise_a_jour');
        }

        Schema::table('retraits', function (Blueprint $table) {
            $table->dropColumn('operateur');
        });
    }
};
