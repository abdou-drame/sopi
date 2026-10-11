<?php

use App\Enums\Metier;
use App\Enums\StatutJustificatif;
use App\Enums\TypeDocument;
use App\Models\Justificatif;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('relations', function () {
    it('relie un utilisateur à son profil prestataire (1-1)', function () {
        $prestataire = Prestataire::factory()->create();

        expect($prestataire->user)->toBeInstanceOf(User::class)
            ->and($prestataire->user->prestataire->is($prestataire))->toBeTrue()
            ->and($prestataire->user->id)->toMatch('/^[0-9a-f-]{36}$/')
            ->and($prestataire->id)->toMatch('/^[0-9a-f-]{36}$/');
    });

    it('refuse un second profil prestataire pour le même utilisateur', function () {
        $user = User::factory()->prestataire()->create();
        Prestataire::factory()->create(['user_id' => $user->id]);

        expect(fn () => Prestataire::factory()->create(['user_id' => $user->id]))->toThrow(QueryException::class);
        expect(Prestataire::count())->toBe(1);
    });

    it('relie un prestataire à ses justificatifs (1-n) et chaque justificatif à son prestataire', function () {
        $prestataire = Prestataire::factory()->create();
        Justificatif::factory()->count(3)->create(['prestataire_id' => $prestataire->id]);
        Justificatif::factory()->create(); // celui d'un autre prestataire

        expect($prestataire->justificatifs)->toHaveCount(3)
            ->and($prestataire->justificatifs->first()->prestataire->is($prestataire))->toBeTrue();
    });

    it('relie un justificatif à l\'administrateur vérificateur', function () {
        $admin = User::factory()->administrateur()->create();
        $justificatif = Justificatif::factory()->valide($admin)->create();

        expect($justificatif->verificateur->is($admin))->toBeTrue()
            ->and($justificatif->statut)->toBe(StatutJustificatif::Valide);
    });

    it('supprime le profil et les justificatifs avec l\'utilisateur', function () {
        $justificatif = Justificatif::factory()->create();
        $justificatif->prestataire->user->delete();

        expect(Prestataire::count())->toBe(0)->and(Justificatif::count())->toBe(0);
    });

    it('garde le justificatif quand l\'administrateur vérificateur est supprimé', function () {
        $admin = User::factory()->administrateur()->create();
        $justificatif = Justificatif::factory()->valide($admin)->create();

        $admin->delete();

        expect($justificatif->fresh()->verifie_par)->toBeNull();
    });
});

describe('valeurs par défaut', function () {
    it('initialise le solde à 0, les photos à vide et le hachage à null', function () {
        $prestataire = Prestataire::factory()->create()->fresh();

        expect($prestataire->solde)->toBe('0.00')
            ->and($prestataire->photos_atelier)->toBe([])
            ->and($prestataire->jeton_suivi_hash)->toBeNull();
    });

    it('applique les mêmes défauts sans passer par la factory', function () {
        $user = User::factory()->prestataire()->create();
        $prestataire = new Prestataire([
            'metier' => Metier::Coiffure, 'quartier' => 'Malika', 'nom_entreprise' => 'Salon Awa', 'adresse' => 'Rue 10',
        ]);
        $prestataire->user_id = $user->id;
        $prestataire->save();

        $prestataire = $prestataire->fresh();
        expect($prestataire->solde)->toBe('0.00')->and($prestataire->photos_atelier)->toBe([]);
    });

    it('initialise le statut d\'un justificatif à EN_ATTENTE sans motif ni vérificateur', function () {
        $justificatif = Justificatif::factory()->create()->fresh();

        expect($justificatif->statut)->toBe(StatutJustificatif::EnAttente)
            ->and($justificatif->motif_rejet)->toBeNull()
            ->and($justificatif->verifie_par)->toBeNull()
            ->and($justificatif->date_soumission)->not->toBeNull();
    });

    it('conserve un rejet avec son motif', function () {
        $justificatif = Justificatif::factory()->rejete('Photo floue.')->create()->fresh();

        expect($justificatif->statut)->toBe(StatutJustificatif::Rejete)
            ->and($justificatif->motif_rejet)->toBe('Photo floue.');
    });
});

describe('types et montants', function () {
    it('convertit métier, type de document et statut en enums', function () {
        $justificatif = Justificatif::factory()->create(['type_document' => TypeDocument::Cni]);
        $prestataire = $justificatif->prestataire->fresh();

        expect($prestataire->metier)->toBeInstanceOf(Metier::class)
            ->and($justificatif->fresh()->type_document)->toBe(TypeDocument::Cni);
    });

    it('stocke le solde en decimal(12,2)', function () {
        $prestataire = Prestataire::factory()->create();
        $prestataire->forceFill(['solde' => 12345.5])->save();

        expect($prestataire->fresh()->solde)->toBe('12345.50');
    });

    it('stocke les photos de l\'atelier comme une liste JSON de chemins', function () {
        $prestataire = Prestataire::factory()->create(['photos_atelier' => ['ateliers/a.jpg', 'ateliers/b.jpg']]);

        expect($prestataire->fresh()->photos_atelier)->toBe(['ateliers/a.jpg', 'ateliers/b.jpg']);
    });

    it('a les colonnes attendues', function () {
        expect(Schema::getColumnListing('prestataires'))->toContain(
            'id', 'user_id', 'metier', 'quartier', 'nom_entreprise', 'adresse', 'photos_atelier', 'solde', 'jeton_suivi_hash'
        );
        expect(Schema::getColumnListing('justificatifs'))->toContain(
            'id', 'prestataire_id', 'verifie_par', 'type_document', 'chemin_fichier', 'statut', 'motif_rejet', 'date_soumission'
        );
    });
});

describe('données sensibles', function () {
    it('n\'expose jamais le chemin du fichier d\'un justificatif', function () {
        $justificatif = Justificatif::factory()->create(['chemin_fichier' => 'justificatifs/secret.pdf']);

        expect($justificatif->toArray())->not->toHaveKey('chemin_fichier')
            ->and($justificatif->toJson())->not->toContain('secret.pdf')
            ->and($justificatif->fresh()->toJson())->not->toContain('secret.pdf')
            ->and($justificatif->fresh()->chemin_fichier)->toBe('justificatifs/secret.pdf'); // lisible côté serveur
    });

    it('n\'expose jamais le hachage du lien de suivi', function () {
        $prestataire = Prestataire::factory()->create();
        $prestataire->forceFill(['jeton_suivi_hash' => hash('sha256', 'jeton-factice')])->save();

        expect($prestataire->toArray())->not->toHaveKey('jeton_suivi_hash')
            ->and($prestataire->fresh()->toJson())->not->toContain(hash('sha256', 'jeton-factice'))
            ->and($prestataire->fresh()->jeton_suivi_hash)->toBe(hash('sha256', 'jeton-factice'));
    });

    it('n\'expose pas ces champs via les relations chargées', function () {
        $justificatif = Justificatif::factory()->create();
        $justificatif->prestataire->forceFill(['jeton_suivi_hash' => 'abc'])->save();

        $json = $justificatif->fresh()->load('prestataire')->toJson();
        expect($json)->not->toContain('chemin_fichier')->and($json)->not->toContain('jeton_suivi_hash');
    });

    it('ne permet pas d\'assigner en masse le solde, le hachage, le statut ni le vérificateur', function () {
        $prestataire = Prestataire::factory()->create();
        $prestataire->update(['solde' => 999999, 'jeton_suivi_hash' => 'x', 'nom_entreprise' => 'Nouveau nom']);

        $justificatif = Justificatif::factory()->create();
        $justificatif->update(['statut' => 'valide', 'verifie_par' => User::factory()->create()->id, 'motif_rejet' => 'x']);

        $prestataire->refresh();
        $justificatif->refresh();
        expect($prestataire->solde)->toBe('0.00')
            ->and($prestataire->jeton_suivi_hash)->toBeNull()
            ->and($prestataire->nom_entreprise)->toBe('Nouveau nom')
            ->and($justificatif->statut)->toBe(StatutJustificatif::EnAttente)
            ->and($justificatif->verifie_par)->toBeNull()
            ->and($justificatif->motif_rejet)->toBeNull();
    });
});
