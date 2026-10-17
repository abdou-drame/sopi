<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Référentiel des quartiers proposés à l'inscription d'un prestataire. Il n'est pas une contrainte :
        // prestataires.quartier reste un texte libre.
        Schema::create('quartiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom', 100)->unique();
            $table->timestamps();
        });

        // Quartiers de départ insérés ici : le déploiement ne lance que `migrate --force`, pas les seeders.
        // Insertion idempotente, comparaison sans tenir compte de la casse.
        $maintenant = now();
        foreach (['Keur Massar Nord', 'Keur Massar Sud'] as $nom) {
            if (DB::table('quartiers')->whereRaw('lower(nom) = ?', [mb_strtolower($nom)])->exists()) {
                continue;
            }
            DB::table('quartiers')->insert([
                'id' => (string) Str::uuid(),
                'nom' => $nom,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quartiers');
    }
};
