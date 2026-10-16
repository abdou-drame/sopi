<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal des mouvements du solde d'un prestataire, écrit dans la même transaction que la modification du solde.
        Schema::create('mouvements_portefeuille', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prestataire_id')->constrained('prestataires')->restrictOnDelete();
            // credit, retrait, remboursement_retrait (enum TypeMouvementPortefeuille). Le montant est toujours positif.
            $table->string('type', 30);
            $table->decimal('montant', 12, 2);
            $table->foreignUuid('reservation_id')->nullable()->constrained('reservations')->restrictOnDelete();
            $table->foreignUuid('retrait_id')->nullable()->constrained('retraits')->restrictOnDelete();
            $table->string('libelle');
            $table->decimal('solde_apres', 12, 2);
            $table->timestamps();

            $table->index(['prestataire_id', 'created_at']);
            // Un seul mouvement de chaque type par réservation / par retrait : rend la reconstitution et les reprises idempotentes.
            $table->unique(['type', 'reservation_id']);
            $table->unique(['type', 'retrait_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_portefeuille');
    }
};
