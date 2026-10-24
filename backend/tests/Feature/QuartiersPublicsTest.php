<?php

use App\Enums\StatutCompte;
use App\Models\Prestataire;
use App\Models\Quartier;
use App\Models\User;
use Database\Seeders\QuartierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function prestataireAuQuartier(string $quartier, StatutCompte $statut = StatutCompte::Actif): Prestataire
{
    return Prestataire::factory()
        ->for(User::factory()->prestataire()->statut($statut), 'user')
        ->create(['quartier' => $quartier]);
}

describe('GET /quartiers', function () {
    it('est public et renvoie une liste vide sans prestataire', function () {
        $this->getJson('/api/v1/quartiers')->assertOk()->assertExactJson(['data' => []]);
    });

    it('liste les quartiers distincts triés, sans doublon de casse ni d\'espaces', function () {
        prestataireAuQuartier('Malika');
        prestataireAuQuartier('malika ');
        prestataireAuQuartier('Keur Massar Nord');
        prestataireAuQuartier('keur massar nord');
        prestataireAuQuartier('Jaxaay');
        prestataireAuQuartier('Édouard');

        $quartiers = $this->getJson('/api/v1/quartiers')->assertOk()->json('data');

        expect($quartiers)->toHaveCount(4)
            ->and(array_map('mb_strtolower', $quartiers))->toBe(['édouard', 'jaxaay', 'keur massar nord', 'malika'])
            ->and($quartiers)->toBe(array_map('trim', $quartiers));
    });

    it('ignore les prestataires dont le compte n\'est pas actif', function () {
        prestataireAuQuartier('Malika');
        prestataireAuQuartier('Jaxaay', StatutCompte::Suspendu);
        prestataireAuQuartier('Yeumbeul Nord', StatutCompte::EnAttenteValidation);
        prestataireAuQuartier('Yeumbeul Sud', StatutCompte::Banni);

        $this->getJson('/api/v1/quartiers')->assertOk()->assertExactJson(['data' => ['Malika']]);
    });
});

describe('GET /quartiers sans paramètre (liste de la recherche)', function () {
    it('ignore le référentiel', function () {
        // Les migrations ont déjà inséré les trois quartiers de départ.
        expect(Quartier::count())->toBe(3);

        $this->getJson('/api/v1/quartiers')->assertOk()->assertExactJson(['data' => []]);

        prestataireAuQuartier('Malika');
        $this->getJson('/api/v1/quartiers')->assertOk()->assertExactJson(['data' => ['Malika']]);
    });

    it('ignore tous=0, tous=false ou une valeur inconnue', function () {
        prestataireAuQuartier('Malika');

        foreach (['0', 'non', 'false', 'abc', ''] as $valeur) {
            $this->getJson('/api/v1/quartiers?tous='.$valeur)->assertOk()->assertExactJson(['data' => ['Malika']]);
        }
    });
});

describe('GET /quartiers?tous=1 (liste complète)', function () {
    it('renvoie le référentiel quand aucun prestataire n\'est actif', function () {
        $this->getJson('/api/v1/quartiers?tous=1')->assertOk()->assertExactJson(['data' => ['Jaxaay', 'Keur Massar Nord', 'Keur Massar Sud']]);
    });

    it('accepte aussi tous=true', function () {
        $this->getJson('/api/v1/quartiers?tous=true')->assertOk()->assertExactJson(['data' => ['Jaxaay', 'Keur Massar Nord', 'Keur Massar Sud']]);
    });

    it('réunit référentiel et quartiers des prestataires actifs, sans doublon de casse, d\'accent ni d\'espaces', function () {
        Quartier::create(['nom' => 'Médina Test']);
        prestataireAuQuartier('keur massar  nord');
        prestataireAuQuartier('MEDINA TEST ');
        prestataireAuQuartier('Édouard');
        prestataireAuQuartier('Zone Libre');

        $this->getJson('/api/v1/quartiers?tous=1')->assertOk()->assertExactJson(['data' => [
            'Édouard', 'Jaxaay', 'Keur Massar Nord', 'Keur Massar Sud', 'Médina Test', 'Zone Libre',
        ]]);
    });

    it('n\'ajoute pas le quartier libre d\'un prestataire non actif', function () {
        prestataireAuQuartier('Quartier Attente', StatutCompte::EnAttenteValidation);
        prestataireAuQuartier('Quartier Refuse', StatutCompte::Refuse);
        prestataireAuQuartier('Quartier Suspendu', StatutCompte::Suspendu);
        prestataireAuQuartier('Quartier Banni', StatutCompte::Banni);

        $this->getJson('/api/v1/quartiers?tous=1')->assertOk()->assertExactJson(['data' => ['Jaxaay', 'Keur Massar Nord', 'Keur Massar Sud']]);
    });
});

describe('Référentiel des quartiers : migration et seeder', function () {
    it('relancer la migration ne crée pas de doublon', function () {
        $migration = require database_path('migrations/2026_11_03_000001_create_quartiers_table.php');
        $migration->up();
        $migration->up();

        expect(Quartier::orderBy('nom')->pluck('nom')->all())->toBe(['Jaxaay', 'Keur Massar Nord', 'Keur Massar Sud']);
    });

    it('la migration ne recrée pas un nom déjà présent sous une autre casse', function () {
        DB::table('quartiers')->delete();
        Quartier::create(['nom' => 'keur massar nord']);

        $migration = require database_path('migrations/2026_11_03_000001_create_quartiers_table.php');
        $migration->up();

        expect(Quartier::orderBy('nom')->pluck('nom')->all())->toEqualCanonicalizing(['keur massar nord', 'Keur Massar Sud']);
    });

    it('la migration de Jaxaay est idempotente, ignore la casse et ne touche à aucun autre quartier', function () {
        DB::table('quartiers')->delete();
        Quartier::create(['nom' => 'JAXAAY']);
        Quartier::create(['nom' => 'Quartier Utilisé']);

        $migration = require database_path('migrations/2026_11_05_000001_ajoute_jaxaay_aux_quartiers.php');
        $migration->up();
        $migration->up();

        expect(Quartier::orderBy('nom')->pluck('nom')->all())->toBe(['JAXAAY', 'Quartier Utilisé']);
    });

    it('le seeder pose exactement trois quartiers et reste idempotent', function () {
        DB::table('quartiers')->delete();

        $this->seed(QuartierSeeder::class);
        $this->seed(QuartierSeeder::class);

        expect(Quartier::NOMS_DE_DEPART)->toBe(['Keur Massar Nord', 'Keur Massar Sud', 'Jaxaay'])
            ->and(Quartier::orderBy('nom')->pluck('nom')->all())->toBe(['Jaxaay', 'Keur Massar Nord', 'Keur Massar Sud']);
    });

    it('le seeder ne supprime ni ne renomme un quartier déjà présent', function () {
        Quartier::create(['nom' => 'Malika']);

        $this->seed(QuartierSeeder::class);

        expect(Quartier::orderBy('nom')->pluck('nom')->all())->toBe(['Jaxaay', 'Keur Massar Nord', 'Keur Massar Sud', 'Malika']);
    });
});
