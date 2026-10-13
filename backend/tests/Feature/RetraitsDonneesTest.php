<?php

use App\Enums\StatutRetrait;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Retrait;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('solde du prestataire', function () {
    it('vaut 0 par défaut', function () {
        $prestataire = Prestataire::factory()->create()->fresh();

        expect($prestataire->solde)->toBe('0.00');
    });

    it('est posé par avoirSolde', function () {
        $prestataire = Prestataire::factory()->avoirSolde(12500)->create()->fresh();

        expect($prestataire->solde)->toBe('12500.00');
    });

    it('n\'est pas assignable en masse', function () {
        $prestataire = (new Prestataire)->fill(['solde' => 5000, 'quartier' => 'Malika']);

        expect($prestataire->solde)->toBe('0.00')
            ->and($prestataire->quartier)->toBe('Malika');
    });

    it('refuse un solde négatif, à l\'insertion comme à la mise à jour', function () {
        $prestataire = Prestataire::factory()->avoirSolde(1000)->create();

        expect(fn () => Prestataire::factory()->avoirSolde(-1)->create())->toThrow(QueryException::class);
        expect(fn () => DB::table('prestataires')->where('id', $prestataire->id)->update(['solde' => -0.01]))->toThrow(QueryException::class);
        expect($prestataire->fresh()->solde)->toBe('1000.00');
    });

    it('accepte un solde nul et un débit exact du solde', function () {
        $prestataire = Prestataire::factory()->avoirSolde(1000)->create();

        DB::table('prestataires')->where('id', $prestataire->id)->update(['solde' => DB::raw('solde - 1000')]);

        expect($prestataire->fresh()->solde)->toBe('0.00');
    });

    it('refuse un débit supérieur au solde (mise à jour atomique)', function () {
        $prestataire = Prestataire::factory()->avoirSolde(1000)->create();

        expect(fn () => DB::table('prestataires')->where('id', $prestataire->id)->update(['solde' => DB::raw('solde - 1000.01')]))->toThrow(QueryException::class);
    });

    it('ne figure pas dans les champs assignables du modèle', function () {
        expect((new Prestataire)->getFillable())->not->toContain('solde');
    });
});

describe('table retraits', function () {
    it('a les colonnes prévues', function () {
        expect(Schema::getColumnListing('retraits'))->toEqualCanonicalizing([
            'id', 'prestataire_id', 'montant', 'statut', 'telephone_versement', 'reference_dexpay', 'traite_le', 'recredite_le', 'created_at', 'updated_at',
        ]);
    });

    it('a les index prévus', function () {
        $colonnes = collect(Schema::getIndexes('retraits'))->map(fn ($i) => implode(',', $i['columns']))->all();

        expect($colonnes)->toContain('prestataire_id,created_at', 'statut,created_at', 'reference_dexpay');
    });

    it('crée un retrait via la factory : UUID, statut en_cours, montant décimal', function () {
        $retrait = Retrait::factory()->create()->fresh();

        expect($retrait->id)->toBeUuid()
            ->and($retrait->statut)->toBe(StatutRetrait::EnCours)
            ->and($retrait->montant)->toBe('5000.00')
            ->and($retrait->reference_dexpay)->toBeNull()
            ->and($retrait->traite_le)->toBeNull()
            ->and($retrait->recredite_le)->toBeNull()
            ->and($retrait->created_at)->not->toBeNull()
            ->and($retrait->prestataire)->toBeInstanceOf(Prestataire::class);
    });

    it('applique le statut en_cours par défaut en base', function () {
        $retrait = new Retrait;
        $retrait->prestataire_id = Prestataire::factory()->create()->id;
        $retrait->montant = 2000;
        $retrait->telephone_versement = '+221770000000';
        $retrait->save();

        expect($retrait->fresh()->statut)->toBe(StatutRetrait::EnCours);
    });

    it('fournit des états cohérents', function () {
        $reussi = Retrait::factory()->reussi()->create()->fresh();
        $echoue = Retrait::factory()->echoue()->create()->fresh();

        expect($reussi->statut)->toBe(StatutRetrait::Reussi)
            ->and($reussi->traite_le)->not->toBeNull()
            ->and($reussi->recredite_le)->toBeNull()
            ->and($echoue->statut)->toBe(StatutRetrait::Echoue)
            ->and($echoue->traite_le)->not->toBeNull();
    });

    it('n\'est pas assignable en masse', function () {
        expect(fn () => Retrait::query()->create(['montant' => 1000]))->toThrow(MassAssignmentException::class);
    });

    it('est relié au prestataire (hasMany)', function () {
        $prestataire = Prestataire::factory()->create();
        Retrait::factory()->count(2)->create(['prestataire_id' => $prestataire->id]);

        expect($prestataire->retraits)->toHaveCount(2);
    });

    it('empêche de supprimer un prestataire qui a des retraits', function () {
        $retrait = Retrait::factory()->create();

        expect(fn () => DB::table('prestataires')->where('id', $retrait->prestataire_id)->delete())->toThrow(QueryException::class);
    });
});

describe('contraintes de cohérence des retraits', function () {
    it('refuse un montant nul ou négatif', function (string $montant) {
        expect(fn () => Retrait::factory()->create(['montant' => $montant]))->toThrow(QueryException::class);
    })->with(['0.00', '-100.00']);

    it('n\'impose pas les bornes de configuration en base', function (string $montant) {
        Retrait::factory()->create(['montant' => $montant]);

        expect(Retrait::count())->toBe(1);
    })->with(['1.00', '999.00', '600000.00']);

    it('refuse un statut invalide', function () {
        $retrait = Retrait::factory()->create();

        expect(fn () => DB::table('retraits')->where('id', $retrait->id)->update(['statut' => 'inconnu']))->toThrow(QueryException::class);
        expect(fn () => DB::table('retraits')->insert([
            'id' => (string) Str::uuid(), 'prestataire_id' => $retrait->prestataire_id,
            'montant' => 1000, 'statut' => 'inconnu', 'telephone_versement' => '+221770000000',
        ]))->toThrow(QueryException::class);
    });

    it('refuse une mise à jour qui rend le montant incohérent', function () {
        $retrait = Retrait::factory()->create();

        expect(fn () => DB::table('retraits')->where('id', $retrait->id)->update(['montant' => 0]))->toThrow(QueryException::class);
    });

    it('a une référence DexPay unique, vide possible pour plusieurs retraits', function () {
        Retrait::factory()->count(2)->create();
        Retrait::factory()->reussi()->create(['reference_dexpay' => 'DXP-RET-UNIQUE']);

        expect(Retrait::whereNull('reference_dexpay')->count())->toBe(2);
        expect(fn () => Retrait::factory()->reussi()->create(['reference_dexpay' => 'DXP-RET-UNIQUE']))->toThrow(QueryException::class);
    });
});

describe('garde de crédit sur les paiements', function () {
    it('credite_le est vide par défaut et convertie en date', function () {
        $paiement = Paiement::factory()->reussi()->create()->fresh();
        expect($paiement->credite_le)->toBeNull();

        DB::table('paiements')->where('id', $paiement->id)->update(['credite_le' => now()]);

        expect($paiement->fresh()->credite_le)->toBeInstanceOf(CarbonInterface::class);
    });
});

describe('configuration des retraits', function () {
    it('porte les bornes par défaut du cahier des charges', function () {
        expect(config('sopi.retraits.montant_minimum'))->toBe(1000)
            ->and(config('sopi.retraits.montant_maximum'))->toBe(500000);
    });
});
