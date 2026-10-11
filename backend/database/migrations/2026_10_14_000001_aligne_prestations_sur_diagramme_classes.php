<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Le diagramme de classes validé n'a pas de classe « Service » : la Prestation porte elle-même
 * son nom, son domaine (le métier), sa durée et son prix. On supprime donc la table services.
 *
 * La table est reconstruite (copie des lignes en mémoire, recréation, réinsertion) plutôt que modifiée sur place :
 * SQLite ne sait pas retirer une colonne portant une clé étrangère et des index, et ce chemin unique
 * donne les mêmes noms d'index sur SQLite et PostgreSQL. En pratique la table est vide.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Reprise du nom et du domaine depuis le service lié, avant sa suppression.
        $lignes = DB::table('prestations')
            ->join('services', 'services.id', '=', 'prestations.service_id')
            ->get([
                'prestations.id', 'prestations.prestataire_id', 'services.nom', 'services.metier as domaine',
                'prestations.duree_minutes', 'prestations.tarif as prix', 'prestations.actif',
                'prestations.created_at', 'prestations.updated_at',
            ])
            ->map(fn ($ligne) => (array) $ligne)
            ->all();

        Schema::drop('prestations');
        Schema::drop('services');

        Schema::create('prestations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prestataire_id')->constrained('prestataires')->cascadeOnDelete();
            $table->string('nom', 100);
            // Valeur de l'enum Metier ; toujours égale au métier du prestataire propriétaire (garanti par le modèle).
            $table->string('domaine', 20);
            $table->unsignedSmallInteger('duree_minutes');
            // XOF (FCFA), strictement positif.
            $table->decimal('prix', 12, 2);
            // Une prestation se désactive, elle ne se supprime pas.
            $table->boolean('actif')->default(true);
            $table->timestamps();

            // Un prestataire ne peut pas avoir deux prestations du même nom.
            $table->unique(['prestataire_id', 'nom']);
            // Recherche : prestations actives d'un domaine.
            $table->index(['domaine', 'actif']);
        });

        $this->ajouterContraintesPostgres();

        if ($lignes !== []) {
            DB::table('prestations')->insert($lignes);
        }
    }

    public function down(): void
    {
        $lignes = DB::table('prestations')->get()->map(fn ($ligne) => (array) $ligne)->all();

        Schema::drop('prestations');

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('metier', 20);
            $table->string('nom', 100);
            $table->timestamps();

            $table->unique(['metier', 'nom']);
        });

        Schema::create('prestations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prestataire_id')->constrained('prestataires')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $table->decimal('tarif', 12, 2);
            $table->unsignedSmallInteger('duree_minutes');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['prestataire_id', 'service_id']);
            $table->index(['service_id', 'actif']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE prestations ADD CONSTRAINT prestations_tarif_positif CHECK (tarif > 0)');
            DB::statement('ALTER TABLE prestations ADD CONSTRAINT prestations_duree_positive CHECK (duree_minutes > 0)');
        }

        // Un service par couple (domaine, nom) retrouvé dans les prestations ; la description (supprimée en up) est perdue.
        $services = [];
        foreach ($lignes as $ligne) {
            $cle = $ligne['domaine'].'|'.$ligne['nom'];
            $services[$cle] ??= [
                'id' => (string) Str::uuid(), 'metier' => $ligne['domaine'], 'nom' => $ligne['nom'],
                'created_at' => $ligne['created_at'], 'updated_at' => $ligne['updated_at'],
            ];
        }
        if ($services !== []) {
            DB::table('services')->insert(array_values($services));
            DB::table('prestations')->insert(array_map(fn ($ligne) => [
                'id' => $ligne['id'], 'prestataire_id' => $ligne['prestataire_id'],
                'service_id' => $services[$ligne['domaine'].'|'.$ligne['nom']]['id'],
                'tarif' => $ligne['prix'], 'duree_minutes' => $ligne['duree_minutes'], 'actif' => $ligne['actif'],
                'created_at' => $ligne['created_at'], 'updated_at' => $ligne['updated_at'],
            ], $lignes));
        }
    }

    /** PostgreSQL : garde-fous en base. SQLite : le modèle Prestation applique les mêmes règles. */
    private function ajouterContraintesPostgres(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE prestations ADD CONSTRAINT prestations_prix_positif CHECK (prix > 0)');
            DB::statement('ALTER TABLE prestations ADD CONSTRAINT prestations_duree_positive CHECK (duree_minutes > 0)');
        }
    }
};
