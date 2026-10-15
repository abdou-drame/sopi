<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Information secrète nécessaire à l'envoi (ex. lien de suivi du dossier) : toujours chiffrée (Crypt), jamais exposée par
        // l'API, effacée dès que l'envoi a réussi ou a échoué définitivement.
        Schema::table('notifications', function (Blueprint $table) {
            $table->text('secret_chiffre')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('secret_chiffre');
        });
    }
};
