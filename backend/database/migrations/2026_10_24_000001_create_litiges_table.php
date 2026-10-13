<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Classe « Litige » (diagramme de classes) : réclamation d'un client sur une réservation (RF07), avec sa description, ses
        // preuves (table litige_preuves) et la réponse du prestataire (RF17), arbitrée par l'administrateur (RF19) qui la clôt
        // par une décision. Statuts : ouvert puis cloture ; la date de signalement est created_at.
        Schema::create('litiges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained('users')->restrictOnDelete();
            // Dénormalisé (créneau de la réservation) pour retrouver vite les litiges d'un prestataire ; cohérence contrôlée par le service.
            $table->foreignUuid('prestataire_id')->constrained('prestataires')->restrictOnDelete();
            // ouvert, cloture (enum StatutLitige).
            $table->string('statut', 20)->default('ouvert');
            $table->text('description');
            // Version des faits du prestataire (une seule réponse).
            $table->text('reponse_prestataire')->nullable();
            $table->dateTime('reponse_le')->nullable();
            // rejet, avertissement, suspension_temporaire, bannissement_definitif (enum DecisionLitige), motivée par l'administrateur.
            $table->string('decision', 30)->nullable();
            $table->text('motif_decision')->nullable();
            $table->foreignUuid('decide_par_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cloture_le')->nullable();
            $table->timestamps();

            $table->index(['statut', 'created_at'], 'litiges_statut_date_index');
            $table->index(['client_id', 'created_at'], 'litiges_client_date_index');
            $table->index(['prestataire_id', 'created_at'], 'litiges_prestataire_date_index');
            $table->index('reservation_id', 'litiges_reservation_index');
        });

        // Un seul litige ouvert à la fois par réservation : index unique partiel (PostgreSQL et SQLite). Un litige clôturé n'empêche rien.
        DB::statement("CREATE UNIQUE INDEX litiges_un_ouvert_par_reservation ON litiges (reservation_id) WHERE statut = 'ouvert'");

        $this->ajouterGardeFous();
    }

    /** Statut, décision et clôture cohérents, garantis par la base sur les deux moteurs. */
    private function ajouterGardeFous(): void
    {
        $regle = "((statut = 'ouvert' AND decision IS NULL AND motif_decision IS NULL AND decide_par_id IS NULL AND cloture_le IS NULL)
            OR (statut = 'cloture' AND decision IS NOT NULL AND decision IN ('rejet', 'avertissement', 'suspension_temporaire', 'bannissement_definitif')
                AND motif_decision IS NOT NULL AND decide_par_id IS NOT NULL AND cloture_le IS NOT NULL))
            AND ((reponse_prestataire IS NULL AND reponse_le IS NULL) OR (reponse_prestataire IS NOT NULL AND reponse_le IS NOT NULL))";

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE litiges ADD CONSTRAINT litiges_coherence CHECK ({$regle})");

            return;
        }

        // Dans un déclencheur SQLite, les colonnes de la ligne s'écrivent NEW.colonne.
        $regleSqlite = preg_replace('/\b('.implode('|', ['statut', 'decision', 'motif_decision', 'decide_par_id', 'cloture_le', 'reponse_prestataire', 'reponse_le']).')\b/', 'NEW.$1', $regle);

        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("CREATE TRIGGER litiges_coherence_{$nom} BEFORE {$evenement} ON litiges
                WHEN NOT ({$regleSqlite})
                BEGIN SELECT RAISE(ABORT, 'litiges_coherence'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('litiges');
    }
};
