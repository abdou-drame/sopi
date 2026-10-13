<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Signalement par le prestataire et modération par l'administrateur (RF20, modereParId du diagramme de classes) :
        // la décision (supprimer ou conserver) est tracée par l'administrateur et la date. « Supprimer » masque l'avis
        // (masque_le) sans effacer la ligne ; un avis conservé reste visible. masque_le vide = avis visible.
        Schema::table('avis', function (Blueprint $table) {
            $table->dateTime('signale_le')->nullable();
            $table->text('motif_signalement')->nullable();
            $table->dateTime('modere_le')->nullable();
            $table->foreignUuid('modere_par_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('motif_moderation')->nullable();
            $table->dateTime('masque_le')->nullable();

            // Avis à traiter par l'administrateur (signalés, pas encore modérés) et avis visibles d'un prestataire.
            $table->index(['signale_le', 'modere_le'], 'avis_signales_index');
            $table->index(['prestataire_id', 'masque_le'], 'avis_visibilite_index');
        });

        $this->ajouterGardeFous();
    }

    /** Signalement, modération et masquage cohérents, garantis par la base sur les deux moteurs. */
    private function ajouterGardeFous(): void
    {
        $regle = '((signale_le IS NULL AND motif_signalement IS NULL) OR (signale_le IS NOT NULL AND motif_signalement IS NOT NULL))
            AND ((modere_le IS NULL AND modere_par_id IS NULL) OR (modere_le IS NOT NULL AND modere_par_id IS NOT NULL))
            AND (masque_le IS NULL OR modere_le IS NOT NULL)';

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE avis ADD CONSTRAINT avis_moderation_coherente CHECK ({$regle})");

            return;
        }

        // SQLite reconstruit la table pour ajouter la clé étrangère et perd alors les déclencheurs de la note (M8.1) : on les repose.
        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("DROP TRIGGER IF EXISTS avis_note_valide_{$nom}");
            DB::statement("CREATE TRIGGER avis_note_valide_{$nom} BEFORE {$evenement} ON avis
                WHEN NOT (NEW.note BETWEEN 1 AND 5)
                BEGIN SELECT RAISE(ABORT, 'avis_note_valide'); END");
        }

        // Dans un déclencheur SQLite, les colonnes de la ligne s'écrivent NEW.colonne.
        $regleSqlite = preg_replace('/\b('.implode('|', ['signale_le', 'motif_signalement', 'modere_le', 'modere_par_id', 'masque_le']).')\b/', 'NEW.$1', $regle);

        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("CREATE TRIGGER avis_moderation_coherente_{$nom} BEFORE {$evenement} ON avis
                WHEN NOT ({$regleSqlite})
                BEGIN SELECT RAISE(ABORT, 'avis_moderation_coherente'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE avis DROP CONSTRAINT IF EXISTS avis_moderation_coherente');
        } else {
            DB::statement('DROP TRIGGER IF EXISTS avis_moderation_coherente_insertion');
            DB::statement('DROP TRIGGER IF EXISTS avis_moderation_coherente_mise_a_jour');
        }

        Schema::table('avis', function (Blueprint $table) {
            $table->dropForeign(['modere_par_id']);
            $table->dropIndex('avis_signales_index');
            $table->dropIndex('avis_visibilite_index');
            $table->dropColumn(['signale_le', 'motif_signalement', 'modere_le', 'modere_par_id', 'motif_moderation', 'masque_le']);
        });
    }
};
