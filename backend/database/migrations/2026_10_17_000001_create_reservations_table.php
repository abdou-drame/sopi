<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Classe « Reservation » (diagramme de classes). created_at tient lieu de dateCreation.
        // Le tarif n'est pas copié : il vient de la prestation.
        Schema::create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('creneau_id')->constrained('creneaux')->restrictOnDelete();
            $table->foreignUuid('prestation_id')->constrained('prestations')->restrictOnDelete();
            // en_attente, acceptee, confirmee, en_cours, terminee, refusee, annulee (enum StatutReservation).
            $table->string('statut', 20)->default('en_attente');
            $table->text('motif_refus')->nullable();
            // 6 caractères alphanumériques majuscules, généré au paiement réussi.
            $table->string('code_reservation', 6)->nullable()->unique();
            // Attribut technique (absent du diagramme) : départ du délai de paiement de 2 heures.
            $table->dateTime('acceptee_le')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'statut']);
            // Tâches planifiées : réservations sans réponse (en_attente) ou non payées (acceptee).
            $table->index(['statut', 'created_at']);
            $table->index('prestation_id');
        });

        // Au plus une réservation active par créneau : index unique partiel (PostgreSQL et SQLite).
        DB::statement("CREATE UNIQUE INDEX reservations_une_active_par_creneau ON reservations (creneau_id) WHERE statut NOT IN ('refusee', 'annulee')");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
