<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Classe « Retrait » (diagramme de classes) : montant, statut, date (created_at) ; le numéro mobile money vient de la
        // fiche d'usage RF23. Les bornes de montant (1 000 / 500 000) ne sont PAS ici : elles viennent de la configuration.
        Schema::create('retraits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prestataire_id')->constrained('prestataires')->restrictOnDelete();
            $table->decimal('montant', 12, 2);
            // en_cours, reussi, echoue (enum StatutRetrait).
            $table->string('statut', 20)->default('en_cours');
            // Numéro mobile money (Wave ou Orange Money) qui reçoit le versement.
            $table->string('telephone_versement', 20);
            // Référence de l'opération chez le prestataire de paiement ; l'unicité crée déjà son index.
            $table->string('reference_dexpay')->nullable()->unique();
            // Attributs techniques (absents du diagramme) : date de traitement du résultat, et garde du recrédit unique.
            $table->dateTime('traite_le')->nullable();
            $table->dateTime('recredite_le')->nullable();
            $table->timestamps();

            $table->index(['prestataire_id', 'created_at']);
            $table->index(['statut', 'created_at']);
        });

        $this->ajouterGardeFous();
    }

    /** Cohérence du montant et du statut garantie par la base, sur les deux moteurs. */
    private function ajouterGardeFous(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE retraits ADD CONSTRAINT retraits_coherence CHECK (montant > 0 AND statut IN ('en_cours', 'reussi', 'echoue'))");

            return;
        }

        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("CREATE TRIGGER retraits_coherence_{$nom} BEFORE {$evenement} ON retraits
                WHEN NOT (NEW.montant > 0 AND NEW.statut IN ('en_cours', 'reussi', 'echoue'))
                BEGIN SELECT RAISE(ABORT, 'retraits_coherence'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retraits');
    }
};
