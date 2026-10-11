<?php

use App\Enums\Metier;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('services (référentiel)', function () {
    it('est créé par sa factory avec un UUID et un métier typé', function () {
        $service = Service::factory()->metier(Metier::Coiffure)->create();

        expect($service->id)->toBeString()->toHaveLength(36)
            ->and($service->fresh()->metier)->toBe(Metier::Coiffure);
    });

    it('refuse deux fois le même nom dans un même métier, mais pas dans deux métiers', function () {
        Service::factory()->metier(Metier::Mecanique)->create(['nom' => 'Diagnostic']);
        Service::factory()->metier(Metier::Coiffure)->create(['nom' => 'Diagnostic']);

        expect(fn () => Service::factory()->metier(Metier::Mecanique)->create(['nom' => 'Diagnostic']))
            ->toThrow(QueryException::class);
    });

    it('expose les prestations qui le proposent', function () {
        $service = Service::factory()->create();
        Prestation::factory()->count(2)->create(['service_id' => $service->id]);

        expect($service->prestations)->toHaveCount(2);
    });

    it('ne peut pas être supprimé tant qu\'un prestataire le propose', function () {
        $prestation = Prestation::factory()->create();

        expect(fn () => $prestation->service->delete())->toThrow(QueryException::class);
    });
});

describe('prestations (offre d\'un prestataire)', function () {
    it('relie un prestataire et un service, avec ses valeurs par défaut', function () {
        $prestataire = Prestataire::factory()->create();
        $service = Service::factory()->create();

        $prestation = $prestataire->prestations()->create(['service_id' => $service->id, 'tarif' => 7500, 'duree_minutes' => 45]);

        expect($prestation->fresh())
            ->actif->toBeTrue()
            ->tarif->toBe('7500.00')
            ->duree_minutes->toBe(45)
            ->description->toBeNull()
            ->and($prestation->prestataire->is($prestataire))->toBeTrue()
            ->and($prestation->service->is($service))->toBeTrue()
            ->and($prestataire->prestations)->toHaveCount(1);
    });

    it('conserve un tarif décimal avec deux décimales', function () {
        $prestation = Prestation::factory()->create(['tarif' => 12500.5]);

        expect($prestation->fresh()->tarif)->toBe('12500.50');
    });

    it('refuse un tarif nul ou négatif', function (float $tarif) {
        expect(fn () => Prestation::factory()->create(['tarif' => $tarif]))
            ->toThrow(InvalidArgumentException::class, 'supérieur à 0');
        expect(Prestation::count())->toBe(0);
    })->with([0, -1, -2500.50]);

    it('refuse un tarif ramené à 0 lors d\'une modification', function () {
        $prestation = Prestation::factory()->create(['tarif' => 5000]);

        expect(fn () => $prestation->update(['tarif' => 0]))->toThrow(InvalidArgumentException::class);
        expect($prestation->fresh()->tarif)->toBe('5000.00');
    });

    it('refuse une durée nulle', function () {
        expect(fn () => Prestation::factory()->create(['duree_minutes' => 0]))
            ->toThrow(InvalidArgumentException::class, 'durée');
    });

    it('refuse le même service deux fois chez un prestataire, mais l\'accepte chez un autre', function () {
        $service = Service::factory()->create();
        $premier = Prestataire::factory()->create();
        Prestation::factory()->create(['prestataire_id' => $premier->id, 'service_id' => $service->id]);

        expect(fn () => Prestation::factory()->create(['prestataire_id' => $premier->id, 'service_id' => $service->id]))
            ->toThrow(QueryException::class);

        Prestation::factory()->create(['prestataire_id' => Prestataire::factory()->create()->id, 'service_id' => $service->id]);
        expect(Prestation::count())->toBe(2);
    });

    it('permet de proposer plusieurs services différents', function () {
        $prestataire = Prestataire::factory()->create();
        Prestation::factory()->count(3)->create(['prestataire_id' => $prestataire->id]);

        expect($prestataire->prestations)->toHaveCount(3);
    });

    it('est supprimée avec son prestataire', function () {
        $prestation = Prestation::factory()->create();

        $prestation->prestataire->delete();

        expect(Prestation::count())->toBe(0)->and(Service::count())->toBe(1);
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

        $prestation = $prestataire->prestations()->create([
            'prestataire_id' => $autre->id, 'service_id' => Service::factory()->create()->id, 'tarif' => 1000, 'duree_minutes' => 30,
        ]);

        expect($prestation->prestataire_id)->toBe($prestataire->id);
    });
});

describe('index de recherche', function () {
    it('existent sur les tables du catalogue et sur prestataires', function () {
        $colonnes = fn (string $table) => collect(Schema::getIndexes($table))->map(fn ($i) => $i['columns'])->all();

        expect($colonnes('services'))->toContain(['metier', 'nom'])
            ->and($colonnes('prestations'))->toContain(['service_id', 'actif'])->toContain(['prestataire_id', 'service_id'])
            ->and($colonnes('prestataires'))->toContain(['metier', 'quartier']);
    });
});

describe('ServiceSeeder', function () {
    it('crée les 12 services de départ', function () {
        $this->seed(ServiceSeeder::class);

        expect(Service::count())->toBe(12)
            ->and(Service::where('metier', Metier::Mecanique)->pluck('nom')->sort()->values()->all())
            ->toBe(['Diagnostic', 'Freinage', 'Pneumatiques', 'Vidange'])
            ->and(Service::where('metier', Metier::Coiffure)->pluck('nom')->sort()->values()->all())
            ->toBe(['Coloration', 'Coupe femme', 'Coupe homme', 'Tresses'])
            ->and(Service::where('metier', Metier::Pressing)->pluck('nom')->sort()->values()->all())
            ->toBe(['Chemise', 'Couette', 'Nettoyage à sec', 'Repassage']);
    });

    it('est idempotent : relancé, il ne crée aucun doublon ni ne change les identifiants', function () {
        $this->seed(ServiceSeeder::class);
        $ids = Service::orderBy('nom')->pluck('id')->all();

        $this->seed(ServiceSeeder::class);
        $this->seed(DatabaseSeeder::class);

        expect(Service::count())->toBe(12)->and(Service::orderBy('nom')->pluck('id')->all())->toBe($ids);
    });

    it('conserve les prestations existantes quand il est relancé', function () {
        $this->seed(ServiceSeeder::class);
        $prestation = Prestation::factory()->create(['service_id' => Service::where('nom', 'Vidange')->first()->id]);

        $this->seed(ServiceSeeder::class);

        expect(Prestation::find($prestation->id))->not->toBeNull();
    });

    it('est appelé par le seeder par défaut sans créer d\'utilisateur', function () {
        $this->seed(DatabaseSeeder::class);

        expect(Service::count())->toBe(12)->and(User::count())->toBe(0);
    });
});
