<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justificatifs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prestataire_id')->constrained('prestataires')->cascadeOnDelete();
            // Administrateur qui a validé ou rejeté le justificatif (vide tant qu'il est en attente).
            $table->foreignUuid('verifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type_document', 20);
            $table->string('chemin_fichier');
            $table->string('statut', 20)->default('en_attente');
            $table->text('motif_rejet')->nullable();
            $table->timestamp('date_soumission')->useCurrent();
            $table->timestamps();

            $table->index(['prestataire_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justificatifs');
    }
};
