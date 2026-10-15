<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Message masqué (sans adresse, jeton ni clé, 300 caractères au plus) du dernier échec d'envoi, pour le back-office.
        Schema::table('notifications', function (Blueprint $table) {
            $table->text('dernier_echec_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('dernier_echec_message');
        });
    }
};
