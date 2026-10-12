<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attribut technique (absent du diagramme de classes) : client qui détient le verrou temporaire du créneau.
        Schema::table('creneaux', function (Blueprint $table) {
            $table->foreignUuid('verrouille_par')->nullable()->constrained('users')->nullOnDelete();
            // Retrouver le verrou en cours d'un client.
            $table->index(['verrouille_par', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::table('creneaux', function (Blueprint $table) {
            $table->dropForeign(['verrouille_par']);
            $table->dropIndex(['verrouille_par', 'statut']);
            $table->dropColumn('verrouille_par');
        });
    }
};
