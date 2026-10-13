<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUTS = "('non_applicable', 'en_attente', 'envoye', 'echoue')";

    public function up(): void
    {
        // Classe « Notification » (diagramme de classes) : message d'alerte ou de suivi adressé à un utilisateur. La ligne est aussi
        // le tampon d'envoi : un statut par canal externe (e-mail, push), repris par la commande planifiée tant qu'il reste des essais.
        // jeton_push existe déjà sur users (migration 2026_10_11) : rien à ajouter de ce côté.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            // Valeur de l'enum TypeNotification.
            $table->string('type', 50);
            $table->string('titre', 150);
            $table->text('corps');
            // Identifiants utiles à l'application (réservation, litige...) : jamais de donnée sensible.
            $table->json('donnees')->nullable();
            $table->dateTime('lue_le')->nullable();
            // non_applicable, en_attente, envoye, echoue (enum StatutEnvoi).
            $table->string('canal_email_statut', 20)->default('non_applicable');
            $table->string('canal_push_statut', 20)->default('non_applicable');
            $table->unsignedSmallInteger('tentatives')->default(0);
            // Verrou d'essai : tant qu'il est dans le futur, aucun autre envoi n'est tenté pour cette ligne.
            $table->dateTime('prochain_essai_le')->nullable();
            // Classe de l'erreur du dernier échec (jamais le message, qui pourrait contenir une adresse ou un jeton).
            $table->string('dernier_echec_classe', 150)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'lue_le']);
            // La commande d'envoi cherche les lignes « en_attente » de chaque canal dont l'essai est dû.
            $table->index(['canal_email_statut', 'prochain_essai_le'], 'notifications_envoi_email_index');
            $table->index(['canal_push_statut', 'prochain_essai_le'], 'notifications_envoi_push_index');
        });

        $this->ajouterGardeFous();
    }

    /** Statuts d'envoi valides, garantis par la base sur les deux moteurs. */
    private function ajouterGardeFous(): void
    {
        $statuts = self::STATUTS;

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_statuts_envoi CHECK (canal_email_statut IN {$statuts} AND canal_push_statut IN {$statuts})");

            return;
        }

        foreach (['INSERT' => 'insertion', 'UPDATE' => 'mise_a_jour'] as $evenement => $nom) {
            DB::statement("CREATE TRIGGER notifications_statuts_envoi_{$nom} BEFORE {$evenement} ON notifications
                WHEN NOT (NEW.canal_email_statut IN {$statuts} AND NEW.canal_push_statut IN {$statuts})
                BEGIN SELECT RAISE(ABORT, 'notifications_statuts_envoi'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
