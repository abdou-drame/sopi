<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attribut technique (RF20) : instant d'envoi du rappel de rendez-vous, pour n'envoyer le rappel qu'une seule fois.
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('rappel_envoye_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('rappel_envoye_le');
        });
    }
};
