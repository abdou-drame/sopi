<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Classe « Creneau » : plage de temps d'un prestataire. Dates stockées en UTC.
        Schema::create('creneaux', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prestataire_id')->constrained('prestataires')->cascadeOnDelete();
            $table->dateTime('date_debut');
            $table->dateTime('date_fin');
            // libre, verrouille, reserve, indisponible (enum StatutCreneau).
            $table->string('statut', 20)->default('libre');
            // Fin du verrou temporaire (créneau verrouillé pendant une réservation).
            $table->dateTime('verrou_expire_a')->nullable();
            $table->timestamps();

            // Deux créneaux ne peuvent pas commencer au même instant chez un même prestataire.
            $table->unique(['prestataire_id', 'date_debut']);
            // Agenda d'un prestataire.
            $table->index(['prestataire_id', 'date_debut', 'statut']);
            // Libération des verrous expirés (tâche planifiée).
            $table->index(['statut', 'verrou_expire_a']);
        });

        // PostgreSQL : garde-fou en base. SQLite ne sait pas ajouter une contrainte CHECK après coup ;
        // le modèle Creneau applique la même règle sur tous les moteurs.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE creneaux ADD CONSTRAINT creneaux_fin_apres_debut CHECK (date_fin > date_debut)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creneaux');
    }
};
