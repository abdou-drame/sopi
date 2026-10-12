<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attribut technique (absent du diagramme) : page de paiement à renvoyer si le client rappelle la route.
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('url_paiement', 500)->nullable()->after('reference_dexpay');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('url_paiement');
        });
    }
};
