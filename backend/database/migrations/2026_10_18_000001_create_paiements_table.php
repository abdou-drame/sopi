<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Classe « Paiement » (diagramme de classes). created_at tient lieu de date de création.
        Schema::create('paiements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->decimal('montant_acompte', 12, 2);
            $table->decimal('commission', 12, 2);
            // en_attente, reussi, echoue, rembourse (enum StatutPaiement).
            $table->string('statut', 20)->default('en_attente');
            // Référence du prestataire de paiement (referenceDexPay) ; l'unicité crée déjà son index.
            $table->string('reference_dexpay')->nullable()->unique();
            // Attributs techniques (absents du diagramme).
            $table->dateTime('paye_le')->nullable();
            $table->dateTime('rembourse_le')->nullable();
            $table->timestamps();

            // Tâches et rapports par statut.
            $table->index(['statut', 'created_at']);
            $table->index('reservation_id');
        });

        // Au plus un paiement en cours ou réussi par réservation : index unique partiel (PostgreSQL et SQLite).
        // Un paiement échoué ou remboursé n'empêche pas un nouveau paiement.
        DB::statement("CREATE UNIQUE INDEX paiements_un_actif_par_reservation ON paiements (reservation_id) WHERE statut IN ('en_attente', 'reussi')");

        $this->ajouterGardeFous();
    }

    /** Cohérence des montants et du statut garantie par la base, sur les deux moteurs. */
    private function ajouterGardeFous(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE paiements ADD CONSTRAINT paiements_coherence CHECK (montant_acompte > 0 AND commission >= 0 AND commission <= montant_acompte AND statut IN ('en_attente', 'reussi', 'echoue', 'rembourse'))");

            return;
        }

        // SQLite ne sait pas ajouter une contrainte CHECK après coup : deux déclencheurs équivalents.
        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("CREATE TRIGGER paiements_coherence_{$nom} BEFORE {$evenement} ON paiements
                WHEN NOT (NEW.montant_acompte > 0 AND NEW.commission >= 0 AND NEW.commission <= NEW.montant_acompte AND NEW.statut IN ('en_attente', 'reussi', 'echoue', 'rembourse'))
                BEGIN SELECT RAISE(ABORT, 'paiements_coherence'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
