<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Champ technique (hors diagramme) : « le solde du prestataire a été crédité pour ce paiement ». Garde contre un
        // double crédit (M7.2).
        Schema::table('paiements', function (Blueprint $table) {
            $table->dateTime('credite_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('credite_le');
        });
    }
};
