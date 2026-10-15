<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Champ technique : code HTTP et extrait masqué (300 caractères au plus) du dernier remboursement échoué. Vide quand il réussit.
        Schema::table('paiements', function (Blueprint $table) {
            $table->text('remboursement_derniere_erreur')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('remboursement_derniere_erreur');
        });
    }
};
