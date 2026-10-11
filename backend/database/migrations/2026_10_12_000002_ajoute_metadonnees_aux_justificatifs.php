<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('justificatifs', function (Blueprint $table) {
            // Informations affichées à l'administrateur. Nullables : les dépôts antérieurs ne les ont pas.
            $table->string('nom_original')->nullable();
            $table->string('type_mime', 100)->nullable();
            $table->unsignedBigInteger('taille')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('justificatifs', function (Blueprint $table) {
            $table->dropColumn(['nom_original', 'type_mime', 'taille']);
        });
    }
};
