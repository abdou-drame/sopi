<?php

use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('prestations', function () {
    it('est créée par sa factory avec un UUID et le domaine de son prestataire', function () {
        $prestation = Prestation::factory()->create();

        expect($prestation->id)->toBeString()->toHaveLength(36)
            ->and($prestation->fresh()->domaine)->toBe($prestation->prestataire->metier)
            ->and($prestation->actif)->toBeTrue();
    });

    it('garde, avec la factory, un domaine égal au métier du prestataire fourni', function (string $metier) {
        $prestataire = Prestataire::factory()->metier($metier)->create();

        $prestations = Prestation::factory()->count(3)->create(['prestataire_id' => $prestataire->id]);

        expect($prestations->every(fn (Prestation $p) => $p->fresh()->domaine === $metier))->toBeTrue();
    })->with(['mecanique', 'coiffure', 'pressing']);

    it('est rattachée à son prestataire et reprend son métier comme domaine', function () {
        $prestataire = Prestataire::factory()->metier('coiffure')->create();

        $prestation = $prestataire->prestations()->create(['nom' => 'Coupe homme', 'duree_minutes' => 45, 'prix' => 7500]);

        expect($prestation->fresh())
            ->nom->toBe('Coupe homme')
            ->domaine->toBe('coiffure')
            ->prix->toBe('7500.00')
            ->duree_minutes->toBe(45)
            ->actif->toBeTrue()
            ->and($prestation->prestataire->is($prestataire))->toBeTrue()
            ->and($prestataire->prestations)->toHaveCount(1);
    });

    it('conserve un prix décimal avec deux décimales', function () {
        expect(Prestation::factory()->create(['prix' => 12500.5])->fresh()->prix)->toBe('12500.50');
    });

    it('refuse un prix nul ou négatif', function (float $prix) {
        expect(fn () => Prestation::factory()->create(['prix' => $prix]))
            ->toThrow(InvalidArgumentException::class, 'supérieur à 0');
        expect(Prestation::count())->toBe(0);
    })->with([0, -1, -2500.50]);

    it('refuse un prix ramené à 0 lors d\'une modification', function () {
        $prestation = Prestation::factory()->create(['prix' => 5000]);

        expect(fn () => $prestation->update(['prix' => 0]))->toThrow(InvalidArgumentException::class);
        expect($prestation->fresh()->prix)->toBe('5000.00');
    });

    it('refuse une durée nulle', function () {
        expect(fn () => Prestation::factory()->create(['duree_minutes' => 0]))
            ->toThrow(InvalidArgumentException::class, 'durée');
    });

    it('refuse un domaine qui n est pas le métier du prestataire', function () {
        expect(fn () => Prestation::factory()->create(['domaine' => 'plomberie']))->toThrow(InvalidArgumentException::class);
        expect(Prestation::count())->toBe(0);
    });

    it('refuse un domaine différent du métier du prestataire', function () {
        $prestataire = Prestataire::factory()->metier('mecanique')->create();

        expect(fn () => Prestation::factory()->create(['prestataire_id' => $prestataire->id, 'domaine' => 'pressing']))
            ->toThrow(InvalidArgumentException::class, 'métier de son prestataire');
        expect(Prestation::count())->toBe(0);
    });

    it('ne laisse pas modifier le domaine par assignation de masse', function () {
        $prestation = Prestation::factory()->create();
        $domaine = $prestation->domaine;

        $prestation->update(['domaine' => $domaine === 'mecanique' ? 'pressing' : 'mecanique']);

        expect($prestation->fresh()->domaine)->toBe($domaine);
    });

    it('refuse deux prestations de même nom chez un prestataire, mais l\'accepte chez un autre', function () {
        $premier = Prestataire::factory()->metier('mecanique')->create();
        Prestation::factory()->create(['prestataire_id' => $premier->id, 'nom' => 'Vidange']);

        expect(fn () => Prestation::factory()->create(['prestataire_id' => $premier->id, 'nom' => 'Vidange']))
            ->toThrow(QueryException::class);

        Prestation::factory()->create(['prestataire_id' => $premier->id, 'nom' => 'Freinage']);
        Prestation::factory()->create(['prestataire_id' => Prestataire::factory()->metier('mecanique')->create()->id, 'nom' => 'Vidange']);
        expect(Prestation::count())->toBe(3);
    });

    it('est supprimée avec son prestataire', function () {
        $prestation = Prestation::factory()->create();

        $prestation->prestataire->delete();

        expect(Prestation::count())->toBe(0);
    });

    it('distingue les prestations actives', function () {
        $prestataire = Prestataire::factory()->create();
        Prestation::factory()->create(['prestataire_id' => $prestataire->id]);
        Prestation::factory()->inactive()->create(['prestataire_id' => $prestataire->id]);

        expect($prestataire->prestations()->where('actif', true)->count())->toBe(1);
    });

    it('n\'accepte pas prestataire_id en assignation de masse', function () {
        $autre = Prestataire::factory()->create();
        $prestataire = Prestataire::factory()->create();

        $prestation = $prestataire->prestations()->create(['prestataire_id' => $autre->id, 'nom' => 'Test', 'duree_minutes' => 30, 'prix' => 1000]);

        expect($prestation->prestataire_id)->toBe($prestataire->id);
    });
});

describe('structure de la base', function () {
    it('n\'a plus de table services ni de colonnes obsolètes', function () {
        expect(Schema::hasTable('services'))->toBeFalse()
            ->and(Schema::hasColumns('prestations', ['id', 'prestataire_id', 'nom', 'domaine', 'duree_minutes', 'prix', 'actif']))->toBeTrue()
            ->and(Schema::hasColumn('prestations', 'service_id'))->toBeFalse()
            ->and(Schema::hasColumn('prestations', 'tarif'))->toBeFalse()
            ->and(Schema::hasColumn('prestations', 'description'))->toBeFalse();
    });

    it('a les index de recherche et d\'unicité attendus', function () {
        $colonnes = fn (string $table) => collect(Schema::getIndexes($table))->map(fn ($i) => $i['columns'])->all();

        expect($colonnes('prestations'))->toContain(['prestataire_id', 'nom'])->toContain(['domaine', 'actif'])
            ->and($colonnes('prestataires'))->toContain(['metier', 'quartier']);
    });
});

/** Nombre de migrations à annuler pour revenir juste avant la migration d'alignement (elle et toutes celles qui la suivent). */
function pasJusquaAlignement(): int
{
    $noms = array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php')));

    return count(array_filter($noms, fn ($n) => $n >= '2026_10_14_000001_aligne_prestations_sur_diagramme_classes'));
}

describe('migration d\'alignement', function () {
    it('reprend le nom et le domaine depuis le service lié, puis supprime services', function () {
        $prestataire = Prestataire::factory()->metier('mecanique')->create();

        // Retour à l'ancien schéma (M3.1), avec une prestation existante.
        $this->artisan('migrate:rollback', ['--step' => pasJusquaAlignement()])->assertSuccessful();
        expect(Schema::hasTable('services'))->toBeTrue()->and(Schema::hasColumn('prestations', 'service_id'))->toBeTrue();

        DB::table('services')->insert(['id' => 'a0000000-0000-7000-8000-000000000001', 'metier' => 'mecanique', 'nom' => 'Vidange', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('prestations')->insert([
            'id' => 'b0000000-0000-7000-8000-000000000001', 'prestataire_id' => $prestataire->id, 'service_id' => 'a0000000-0000-7000-8000-000000000001',
            'tarif' => 15000, 'duree_minutes' => 60, 'description' => 'Huile moteur', 'actif' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $prestation = Prestation::findOrFail('b0000000-0000-7000-8000-000000000001');
        expect(Schema::hasTable('services'))->toBeFalse()
            ->and($prestation)->nom->toBe('Vidange')->domaine->toBe('mecanique')
            ->prix->toBe('15000.00')->duree_minutes->toBe(60)->actif->toBeFalse()
            ->and($prestation->prestataire_id)->toBe($prestataire->id);
    });

    it('revient en arrière en recréant un service par couple domaine/nom', function () {
        $prestataire = Prestataire::factory()->metier('coiffure')->create();
        $autre = Prestataire::factory()->metier('coiffure')->create();
        Prestation::factory()->create(['prestataire_id' => $prestataire->id, 'nom' => 'Tresses', 'prix' => 8000]);
        Prestation::factory()->create(['prestataire_id' => $autre->id, 'nom' => 'Tresses', 'prix' => 9000]);

        $this->artisan('migrate:rollback', ['--step' => pasJusquaAlignement()])->assertSuccessful();

        expect(DB::table('services')->count())->toBe(1)
            ->and(DB::table('services')->value('nom'))->toBe('Tresses')
            ->and(DB::table('prestations')->count())->toBe(2)
            ->and((float) DB::table('prestations')->orderBy('tarif')->value('tarif'))->toBe(8000.0);

        $this->artisan('migrate')->assertSuccessful();
        expect(Prestation::count())->toBe(2);
    });
});

describe('DatabaseSeeder', function () {
    it('ne crée aucune donnée factice et peut être relancé', function () {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        expect(User::count())->toBe(0)->and(Prestataire::count())->toBe(0)->and(Prestation::count())->toBe(0);
    });
});
