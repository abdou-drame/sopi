<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestataires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Un seul profil prestataire par utilisateur (relation 1-1).
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('metier', 20);
            $table->string('quartier');
            $table->string('nom_entreprise');
            $table->string('adresse');
            // Liste de chemins de fichiers (photos de l'atelier).
            $table->json('photos_atelier')->default('[]');
            // Le solde est porté par le prestataire (pas de table portefeuilles). XOF.
            $table->decimal('solde', 12, 2)->default(0);
            // Hachage du lien de suivi : le jeton en clair n'est jamais stocké.
            $table->string('jeton_suivi_hash')->nullable()->unique();
            $table->timestamps();

            $table->index(['metier', 'quartier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestataires');
    }
};
