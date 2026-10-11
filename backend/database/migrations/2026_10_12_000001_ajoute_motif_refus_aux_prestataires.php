<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestataires', function (Blueprint $table) {
            // Motif communiqué au prestataire quand son dossier est refusé (renseigné par l'administrateur).
            $table->text('motif_refus')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('prestataires', function (Blueprint $table) {
            $table->dropColumn('motif_refus');
        });
    }
};
