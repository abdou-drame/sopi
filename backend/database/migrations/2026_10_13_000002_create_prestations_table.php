<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Classe « PrestationPrestataire » : un service tel qu'un prestataire le propose (tarif et durée propres).
        Schema::create('prestations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prestataire_id')->constrained('prestataires')->cascadeOnDelete();
            // Un service du référentiel ne se supprime pas tant qu'un prestataire le propose.
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            // XOF (FCFA), strictement positif.
            $table->decimal('tarif', 12, 2);
            $table->unsignedSmallInteger('duree_minutes');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            // Un même service ne peut être proposé qu'une fois par prestataire.
            $table->unique(['prestataire_id', 'service_id']);
            // Recherche : « qui propose ce service, actuellement ? ».
            $table->index(['service_id', 'actif']);
        });

        // PostgreSQL : garde-fou en base. SQLite ne sait pas ajouter une contrainte CHECK après coup ;
        // le modèle Prestation applique les mêmes règles sur tous les moteurs.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE prestations ADD CONSTRAINT prestations_tarif_positif CHECK (tarif > 0)');
            DB::statement('ALTER TABLE prestations ADD CONSTRAINT prestations_duree_positive CHECK (duree_minutes > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prestations');
    }
};
