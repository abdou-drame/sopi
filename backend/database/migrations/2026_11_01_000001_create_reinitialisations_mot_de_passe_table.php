<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jeton de réinitialisation du mot de passe : seul le hachage SHA-256 est conservé, jamais le jeton lui-même.
        // Usage unique (utilise_le) et durée de vie limitée (expire_le, 60 minutes par défaut).
        Schema::create('reinitialisations_mot_de_passe', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('jeton_hash', 64);
            $table->dateTime('expire_le');
            $table->dateTime('utilise_le')->nullable();
            $table->timestamps();

            $table->unique('jeton_hash', 'reinitialisations_mdp_jeton_hash_unique');
            $table->index('user_id', 'reinitialisations_mdp_user_id_index');
            $table->index('expire_le', 'reinitialisations_mdp_expire_le_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reinitialisations_mot_de_passe');
    }
};
