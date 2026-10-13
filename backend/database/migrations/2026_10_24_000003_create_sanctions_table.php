<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Classe « Sanction » (diagramme de classes) : décision disciplinaire infligée à un utilisateur, client ou prestataire
        // (RF19), suite à un litige ou posée directement par l'administrateur. L'historique n'est jamais supprimé : une
        // sanction levée garde sa trace (levee_le, et levee_par_id sauf levée automatique d'une suspension arrivée à échéance).
        Schema::create('sanctions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            // avertissement, suspension_temporaire, bannissement_definitif (enum TypeSanction).
            $table->string('type', 30);
            $table->text('motif');
            // Fin de la suspension temporaire ; vide pour les autres types.
            $table->dateTime('date_fin')->nullable();
            $table->foreignUuid('litige_id')->nullable()->constrained('litiges')->restrictOnDelete();
            $table->foreignUuid('avis_id')->nullable()->constrained('avis')->restrictOnDelete();
            $table->foreignUuid('pose_par_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('levee_le')->nullable();
            $table->foreignUuid('levee_par_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'sanctions_user_date_index');
            $table->index(['type', 'levee_le', 'date_fin'], 'sanctions_echeance_index');
            $table->index('avis_id', 'sanctions_avis_index');
            // Un litige ne produit qu'une sanction (l'unicité d'une colonne nullable laisse passer les sanctions sans litige).
            $table->unique('litige_id', 'sanctions_litige_unique');
        });

        // Une seule sanction de blocage (suspension ou bannissement) active par compte : index unique partiel.
        DB::statement("CREATE UNIQUE INDEX sanctions_un_blocage_actif_par_compte ON sanctions (user_id) WHERE levee_le IS NULL AND type IN ('suspension_temporaire', 'bannissement_definitif')");

        $this->ajouterGardeFous();
    }

    /** Type valide, date de fin si et seulement si suspension, levée cohérente, garantis par la base sur les deux moteurs. */
    private function ajouterGardeFous(): void
    {
        $regle = "type IN ('avertissement', 'suspension_temporaire', 'bannissement_definitif')
            AND ((type = 'suspension_temporaire' AND date_fin IS NOT NULL) OR (type <> 'suspension_temporaire' AND date_fin IS NULL))
            AND (levee_par_id IS NULL OR levee_le IS NOT NULL)";

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sanctions ADD CONSTRAINT sanctions_coherence CHECK ({$regle})");

            return;
        }

        // Dans un déclencheur SQLite, les colonnes de la ligne s'écrivent NEW.colonne.
        $regleSqlite = preg_replace('/\b('.implode('|', ['type', 'date_fin', 'levee_par_id', 'levee_le']).')\b/', 'NEW.$1', $regle);

        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("CREATE TRIGGER sanctions_coherence_{$nom} BEFORE {$evenement} ON sanctions
                WHEN NOT ({$regleSqlite})
                BEGIN SELECT RAISE(ABORT, 'sanctions_coherence'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions');
    }
};
