<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Le référentiel passe à trois quartiers : Keur Massar Nord, Keur Massar Sud (déjà insérés) et Jaxaay.
        // Insertion idempotente, sans rien supprimer ni renommer : les quartiers déjà utilisés restent intacts.
        if (DB::table('quartiers')->whereRaw('lower(nom) = ?', ['jaxaay'])->exists()) {
            return;
        }

        $maintenant = now();
        DB::table('quartiers')->insert([
            'id' => (string) Str::uuid(),
            'nom' => 'Jaxaay',
            'created_at' => $maintenant,
            'updated_at' => $maintenant,
        ]);
    }

    public function down(): void
    {
        // Volontairement vide : on ne supprime jamais un quartier qui peut déjà être utilisé.
    }
};
