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
        // Référentiel des métiers de la plateforme, géré par l'administrateur. `code` est la valeur stockée dans
        // prestataires.metier, services.metier et prestations.domaine ; il ne change jamais une fois créé.
        Schema::create('metiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('libelle', 60);
            // Un métier désactivé n'accepte plus de nouvelles inscriptions ; les prestataires existants le gardent.
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Métiers de départ (CLAUDE.md) insérés ici : le déploiement ne lance que `migrate --force`, pas les seeders.
        $maintenant = now();
        foreach (['mecanique' => 'Mécanique', 'coiffure' => 'Coiffure', 'pressing' => 'Pressing'] as $code => $libelle) {
            DB::table('metiers')->insert([
                'id' => (string) Str::uuid(),
                'code' => $code,
                'libelle' => $libelle,
                'actif' => true,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('metiers');
    }
};
