<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Choix du client (wave, orange_money) à l'initiation du paiement. Information seulement : DexPay ne permet pas de
        // présélectionner l'opérateur à la création de la session, donc ce champ n'a aucun effet sur le paiement.
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('operateur_souhaite', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('operateur_souhaite');
        });
    }
};
