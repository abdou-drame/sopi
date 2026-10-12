<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attributs techniques (absents du diagramme) : « ce paiement doit être remboursé depuis cet instant » et le nombre
        // d'essais de reprise déjà faits par la commande planifiée.
        Schema::table('paiements', function (Blueprint $table) {
            $table->timestamp('remboursement_du_le')->nullable()->index()->after('rembourse_le');
            $table->unsignedSmallInteger('remboursement_tentatives')->default(0)->after('remboursement_du_le');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropIndex(['remboursement_du_le']);
            $table->dropColumn(['remboursement_du_le', 'remboursement_tentatives']);
        });
    }
};
