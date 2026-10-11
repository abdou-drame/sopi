<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Référentiel des services génériques rattachés à un métier (classe « Service » du modèle de classes).
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Même valeur que prestataires.metier (enum Metier).
            $table->string('metier', 20);
            $table->string('nom', 100);
            $table->timestamps();

            // Un service n'existe qu'une fois par métier ; l'index couvre aussi la recherche par métier.
            $table->unique(['metier', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
