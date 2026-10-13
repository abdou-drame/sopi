<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Classe « Avis » (diagramme de classes) : note chiffrée et avis textuel d'un client sur une réservation TERMINEE (RF06,
        // RG30) ; la date de dépôt est created_at. Le signalement par le prestataire et la modération par l'administrateur
        // (modereParId, RF20) ne sont PAS ici : ils viendront avec leur module.
        Schema::create('avis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Un seul avis par réservation : l'index unique est la garantie ultime, même en cas de requêtes simultanées.
            $table->foreignUuid('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained('users')->restrictOnDelete();
            // Dénormalisé (créneau de la réservation) pour agréger vite par prestataire ; cohérence contrôlée par AvisService.
            $table->foreignUuid('prestataire_id')->constrained('prestataires')->restrictOnDelete();
            $table->unsignedTinyInteger('note');
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->unique('reservation_id', 'avis_reservation_unique');
            $table->index(['prestataire_id', 'created_at'], 'avis_prestataire_date_index');
            $table->index('client_id', 'avis_client_index');
        });

        $this->ajouterGardeFous();
    }

    /** Note entière de 1 à 5 garantie par la base, sur les deux moteurs. */
    private function ajouterGardeFous(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE avis ADD CONSTRAINT avis_note_valide CHECK (note BETWEEN 1 AND 5)');

            return;
        }

        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("CREATE TRIGGER avis_note_valide_{$nom} BEFORE {$evenement} ON avis
                WHEN NOT (NEW.note BETWEEN 1 AND 5)
                BEGIN SELECT RAISE(ABORT, 'avis_note_valide'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('avis');
    }
};
