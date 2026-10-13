<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preuves photographiques d'un litige (RF07). Seul le nom du fichier est stocké (disque privé), jamais un chemin.
        Schema::create('litige_preuves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('litige_id')->constrained('litiges')->restrictOnDelete();
            $table->string('fichier');
            $table->timestamps();

            $table->unique('fichier', 'litige_preuves_fichier_unique');
            $table->index(['litige_id', 'created_at'], 'litige_preuves_litige_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('litige_preuves');
    }
};
