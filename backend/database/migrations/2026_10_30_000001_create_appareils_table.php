<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Appareil d'un utilisateur : jeton FCM propre à une installation de l'application. Un jeton n'appartient qu'à un seul
        // utilisateur (le dernier connecté), un utilisateur peut avoir plusieurs appareils.
        Schema::create('appareils', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('jeton', 512);
            // android ou ios (enum PlateformeAppareil).
            $table->string('plateforme', 10);
            $table->dateTime('derniere_activite')->nullable();
            $table->timestamps();

            $table->unique('jeton', 'appareils_jeton_unique');
            $table->index('user_id', 'appareils_user_id_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE appareils ADD CONSTRAINT appareils_plateforme CHECK (plateforme IN ('android', 'ios'))");

            return;
        }

        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("CREATE TRIGGER appareils_plateforme_{$nom} BEFORE {$evenement} ON appareils
                WHEN NOT (NEW.plateforme IN ('android', 'ios'))
                BEGIN SELECT RAISE(ABORT, 'appareils_plateforme'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appareils');
    }
};
