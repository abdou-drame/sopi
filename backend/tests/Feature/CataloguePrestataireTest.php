<?php

use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

const URL_PRESTATIONS = '/api/v1/prestataire/prestations';

function prestataireCatalogue(string $metier = 'mecanique'): Prestataire
{
    return Prestataire::factory()->metier($metier)->create();
}

function agirCommePrestataire(Prestataire $prestataire): void
{
    test()->actingAs($prestataire->user, 'sanctum');
}

function corpsPrestation(array $surcharge = []): array
{
    return array_merge(['nom' => 'Vidange complète', 'duree_minutes' => 60, 'prix' => 15000], $surcharge);
}

describe('accès réservé aux prestataires', function () {
    it('renvoie 401 sans jeton, 403 pour un client et pour un administrateur', function () {
        $prestation = Prestation::factory()->create();
        $routes = [['getJson', URL_PRESTATIONS], ['postJson', URL_PRESTATIONS], ['patchJson', URL_PRESTATIONS.'/'.$prestation->id]];

        foreach ($routes as [$methode, $url]) {
            $this->$methode($url, corpsPrestation())->assertUnauthorized();
        }
        foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $utilisateur) {
            $this->actingAs($utilisateur, 'sanctum');
            foreach ($routes as [$methode, $url]) {
                $this->$methode($url, corpsPrestation())->assertForbidden()
                    ->assertJsonPath('message', "Vous n'avez pas le droit d'effectuer cette action.");
            }
        }
        expect(Prestation::count())->toBe(1);
    });

    it('n\'offre aucune route de suppression', function () {
        $prestation = Prestation::factory()->create();
        agirCommePrestataire($prestation->prestataire);

        $this->deleteJson(URL_PRESTATIONS.'/'.$prestation->id)->assertStatus(405);
        expect(Prestation::count())->toBe(1);
    });
});

describe('POST /prestataire/prestations', function () {
    it('crée une prestation dont le domaine est le métier du prestataire', function (string $metier) {
        $prestataire = prestataireCatalogue($metier);
        agirCommePrestataire($prestataire);

        $this->postJson(URL_PRESTATIONS, corpsPrestation())
            ->assertCreated()
            ->assertExactJson([
                'id' => Prestation::first()->id, 'nom' => 'Vidange complète', 'domaine' => $metier,
                'dureeMinutes' => 60, 'prix' => 15000, 'actif' => true,
            ]);

        expect(Prestation::first())->prestataire_id->toBe($prestataire->id)->domaine->toBe($metier);
    })->with(['mecanique', 'coiffure', 'pressing']);

    it('ignore un domaine envoyé dans la requête', function () {
        $prestataire = prestataireCatalogue('coiffure');
        agirCommePrestataire($prestataire);

        $this->postJson(URL_PRESTATIONS, corpsPrestation(['domaine' => 'mecanique', 'actif' => false, 'prestataire_id' => Prestataire::factory()->create()->id]))
            ->assertCreated()->assertJsonPath('domaine', 'coiffure')->assertJsonPath('actif', true);

        expect(Prestation::first())->domaine->toBe('coiffure')->prestataire_id->toBe($prestataire->id);
    });

    it('retire les espaces superflus du nom', function () {
        agirCommePrestataire(prestataireCatalogue());

        $this->postJson(URL_PRESTATIONS, corpsPrestation(['nom' => "  Coupe   homme \t "]))->assertCreated()->assertJsonPath('nom', 'Coupe homme');
    });

    it('accepte un prix décimal et les bornes de durée', function (int $duree) {
        agirCommePrestataire(prestataireCatalogue());

        $this->postJson(URL_PRESTATIONS, corpsPrestation(['duree_minutes' => $duree, 'prix' => 2500.50]))
            ->assertCreated()->assertJsonPath('prix', 2500.5)->assertJsonPath('dureeMinutes', $duree);
    })->with([1, 1440]);

    it('refuse un nom absent, vide, trop court ou trop long', function (mixed $nom) {
        agirCommePrestataire(prestataireCatalogue());
        $corps = $nom === null ? ['duree_minutes' => 30, 'prix' => 1000] : corpsPrestation(['nom' => $nom]);

        $this->postJson(URL_PRESTATIONS, $corps)->assertUnprocessable()->assertJsonValidationErrors('nom');
        expect(Prestation::count())->toBe(0);
    })->with([null, '', '     ', 'A', ' B ', fn () => str_repeat('a', 101)]);

    it('refuse un doublon exact, puis les doublons de casse et d\'espaces, avec le message français', function (string $nom) {
        $prestataire = prestataireCatalogue();
        Prestation::factory()->create(['prestataire_id' => $prestataire->id, 'nom' => 'Élagage soigné']);
        agirCommePrestataire($prestataire);

        $this->postJson(URL_PRESTATIONS, corpsPrestation(['nom' => $nom]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Vous proposez déjà une prestation avec ce nom.')
            ->assertJsonPath('errors.nom.0', 'Vous proposez déjà une prestation avec ce nom.');
        expect(Prestation::count())->toBe(1);
    })->with(['Élagage soigné', 'élagage soigné', 'ÉLAGAGE SOIGNÉ', '  Élagage soigné  ', 'Élagage   soigné']);

    it('autorise le même nom chez un autre prestataire', function () {
        Prestation::factory()->create(['nom' => 'Vidange complète']);
        agirCommePrestataire(prestataireCatalogue());

        $this->postJson(URL_PRESTATIONS, corpsPrestation())->assertCreated();
        expect(Prestation::count())->toBe(2);
    });

    it('refuse un nom déjà pris par une prestation désactivée', function () {
        $prestataire = prestataireCatalogue();
        Prestation::factory()->inactive()->create(['prestataire_id' => $prestataire->id, 'nom' => 'Freinage']);
        agirCommePrestataire($prestataire);

        $this->postJson(URL_PRESTATIONS, corpsPrestation(['nom' => 'freinage']))->assertUnprocessable()->assertJsonValidationErrors('nom');
    });

    it('refuse un prix absent, nul, négatif, non numérique, trop précis ou trop grand', function (mixed $prix) {
        agirCommePrestataire(prestataireCatalogue());
        $corps = $prix === null ? ['nom' => 'Test', 'duree_minutes' => 30] : corpsPrestation(['prix' => $prix]);

        $this->postJson(URL_PRESTATIONS, $corps)->assertUnprocessable()->assertJsonValidationErrors('prix');
        expect(Prestation::count())->toBe(0);
    })->with([null, 0, 0.0, '0', -1, -2500.5, 'abc', '12,5', 100.123, 10000000000, true]);

    it('refuse une durée absente, nulle, négative, décimale, non numérique ou supérieure à 24 h', function (mixed $duree) {
        agirCommePrestataire(prestataireCatalogue());
        $corps = $duree === null ? ['nom' => 'Test', 'prix' => 1000] : corpsPrestation(['duree_minutes' => $duree]);

        $this->postJson(URL_PRESTATIONS, $corps)->assertUnprocessable()->assertJsonValidationErrors('duree_minutes');
        expect(Prestation::count())->toBe(0);
    })->with([null, 0, -30, 1.5, 'abc', 1441]);

    it('renvoie les messages d\'erreur en français', function () {
        agirCommePrestataire(prestataireCatalogue());

        $this->postJson(URL_PRESTATIONS, corpsPrestation(['nom' => 'A', 'prix' => 0, 'duree_minutes' => 0]))
            ->assertJsonPath('errors.nom.0', 'Le nom de la prestation doit contenir au moins 2 caractères.')
            ->assertJsonPath('errors.prix.0', 'Le prix doit être supérieur à 0.')
            ->assertJsonPath('errors.duree_minutes.0', 'La durée doit être d\'au moins 1 minute.');
    });
});

describe('PATCH /prestataire/prestations/{id}', function () {
    it('modifie un seul champ sans toucher aux autres', function () {
        $prestation = Prestation::factory()->create(['nom' => 'Vidange', 'duree_minutes' => 60, 'prix' => 15000]);
        agirCommePrestataire($prestation->prestataire);

        $this->patchJson(URL_PRESTATIONS.'/'.$prestation->id, ['prix' => 17500])
            ->assertOk()->assertExactJson([
                'id' => $prestation->id, 'nom' => 'Vidange', 'domaine' => $prestation->domaine,
                'dureeMinutes' => 60, 'prix' => 17500, 'actif' => true,
            ]);

        expect($prestation->fresh())->nom->toBe('Vidange')->duree_minutes->toBe(60)->prix->toBe('17500.00');
    });

    it('modifie plusieurs champs à la fois', function () {
        $prestation = Prestation::factory()->create();
        agirCommePrestataire($prestation->prestataire);

        $this->patchJson(URL_PRESTATIONS.'/'.$prestation->id, ['nom' => '  Nouveau   nom ', 'duree_minutes' => 90, 'prix' => 9000.5])
            ->assertOk()->assertJsonPath('nom', 'Nouveau nom')->assertJsonPath('dureeMinutes', 90)->assertJsonPath('prix', 9000.5);
    });

    it('désactive puis réactive une prestation sans la supprimer', function () {
        $prestation = Prestation::factory()->create();
        agirCommePrestataire($prestation->prestataire);
        $url = URL_PRESTATIONS.'/'.$prestation->id;

        $this->patchJson($url, ['actif' => false])->assertOk()->assertJsonPath('actif', false);
        expect($prestation->fresh()->actif)->toBeFalse()->and(Prestation::count())->toBe(1);

        $this->patchJson($url, ['actif' => true])->assertOk()->assertJsonPath('actif', true);
        expect($prestation->fresh()->actif)->toBeTrue();
    });

    it('ne change jamais le domaine ni le propriétaire', function () {
        $prestation = Prestation::factory()->create();
        $domaine = $prestation->domaine;
        agirCommePrestataire($prestation->prestataire);

        $this->patchJson(URL_PRESTATIONS.'/'.$prestation->id, ['nom' => 'Autre nom', 'domaine' => $domaine === 'pressing' ? 'coiffure' : 'pressing', 'prestataire_id' => Prestataire::factory()->create()->id])
            ->assertOk()->assertJsonPath('domaine', $domaine);

        expect($prestation->fresh())->domaine->toBe($domaine)->prestataire_id->toBe($prestation->prestataire_id);
    });

    it('autorise à garder son propre nom, même avec une casse différente', function () {
        $prestation = Prestation::factory()->create(['nom' => 'Vidange']);
        agirCommePrestataire($prestation->prestataire);

        $this->patchJson(URL_PRESTATIONS.'/'.$prestation->id, ['nom' => 'VIDANGE'])->assertOk()->assertJsonPath('nom', 'VIDANGE');
    });

    it('refuse un nom déjà pris par une autre prestation du prestataire', function () {
        $prestataire = prestataireCatalogue();
        Prestation::factory()->create(['prestataire_id' => $prestataire->id, 'nom' => 'Freinage']);
        $prestation = Prestation::factory()->create(['prestataire_id' => $prestataire->id, 'nom' => 'Vidange']);
        agirCommePrestataire($prestataire);

        $this->patchJson(URL_PRESTATIONS.'/'.$prestation->id, ['nom' => ' freinage '])
            ->assertUnprocessable()->assertJsonPath('errors.nom.0', 'Vous proposez déjà une prestation avec ce nom.');
        expect($prestation->fresh()->nom)->toBe('Vidange');
    });

    it('refuse les valeurs invalides sans rien modifier', function (array $corps, string $champ) {
        $prestation = Prestation::factory()->create(['nom' => 'Vidange', 'duree_minutes' => 60, 'prix' => 15000]);
        agirCommePrestataire($prestation->prestataire);

        $this->patchJson(URL_PRESTATIONS.'/'.$prestation->id, $corps)->assertUnprocessable()->assertJsonValidationErrors($champ);
        expect($prestation->fresh())->nom->toBe('Vidange')->duree_minutes->toBe(60)->prix->toBe('15000.00')->actif->toBeTrue();
    })->with([
        'prix nul' => [['prix' => 0], 'prix'],
        'prix négatif' => [['prix' => -10], 'prix'],
        'durée nulle' => [['duree_minutes' => 0], 'duree_minutes'],
        'durée trop longue' => [['duree_minutes' => 5000], 'duree_minutes'],
        'nom trop court' => [['nom' => 'x'], 'nom'],
        'nom vide' => [['nom' => ''], 'nom'],
        'actif invalide' => [['actif' => 'peut-être'], 'actif'],
        'aucun champ' => [[], 'nom'],
        'champs ignorés seulement' => [['domaine' => 'pressing'], 'nom'],
    ]);

    it('renvoie 404 pour la prestation d\'un autre prestataire, sans rien modifier', function () {
        $autre = Prestation::factory()->create(['nom' => 'Secrète', 'prix' => 5000]);
        agirCommePrestataire(prestataireCatalogue());

        $this->patchJson(URL_PRESTATIONS.'/'.$autre->id, ['prix' => 1, 'actif' => false])
            ->assertNotFound()->assertExactJson(['message' => 'Prestation introuvable.']);
        expect($autre->fresh())->prix->toBe('5000.00')->actif->toBeTrue();
    });

    it('répond de la même façon pour une prestation inexistante que pour celle d\'un autre', function () {
        $autre = Prestation::factory()->create();
        agirCommePrestataire(prestataireCatalogue());

        $existante = $this->patchJson(URL_PRESTATIONS.'/'.$autre->id, ['prix' => 1000]);
        $inexistante = $this->patchJson(URL_PRESTATIONS.'/'.fake()->uuid(), ['prix' => 1000]);

        expect($inexistante->status())->toBe($existante->status())->and($inexistante->json())->toBe($existante->json());
        $this->patchJson(URL_PRESTATIONS.'/pas-un-uuid', ['prix' => 1000])->assertNotFound();
    });
});

describe('GET /prestataire/prestations', function () {
    it('liste mes prestations actives et inactives, les plus récentes d\'abord, et seulement les miennes', function () {
        $prestataire = prestataireCatalogue('pressing');
        $ancienne = Prestation::factory()->create(['prestataire_id' => $prestataire->id, 'nom' => 'Ancienne', 'created_at' => now()->subDays(3)]);
        $inactive = Prestation::factory()->inactive()->create(['prestataire_id' => $prestataire->id, 'nom' => 'Inactive', 'created_at' => now()->subDay()]);
        $recente = Prestation::factory()->create(['prestataire_id' => $prestataire->id, 'nom' => 'Récente', 'created_at' => now()]);
        Prestation::factory()->count(2)->create();
        agirCommePrestataire($prestataire);

        $this->getJson(URL_PRESTATIONS)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $recente->id)
            ->assertJsonPath('data.1.id', $inactive->id)
            ->assertJsonPath('data.1.actif', false)
            ->assertJsonPath('data.2.id', $ancienne->id)
            ->assertJsonStructure(['data' => ['*' => ['id', 'nom', 'domaine', 'dureeMinutes', 'prix', 'actif']]])
            ->assertJsonPath('data.0.domaine', 'pressing');
    });

    it('renvoie une liste vide pour un nouveau prestataire', function () {
        agirCommePrestataire(prestataireCatalogue());

        $this->getJson(URL_PRESTATIONS)->assertOk()->assertExactJson(['data' => []]);
    });

    it('ne divulgue aucun identifiant interne', function () {
        $prestation = Prestation::factory()->create();
        agirCommePrestataire($prestation->prestataire);

        $brut = $this->getJson(URL_PRESTATIONS)->assertOk()->getContent();

        expect($brut)->not->toContain('prestataire_id')->not->toContain('created_at')->not->toContain('duree_minutes');
    });
});

describe('mécanisme de rôle partagé', function () {
    it('garde l\'alias admin fonctionnel : refuse client et prestataire, accepte l\'administrateur', function () {
        $url = '/api/v1/admin/prestataires/demandes';

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs(prestataireCatalogue()->user, 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')->getJson($url)->assertOk();
    });

    it('permet de combiner plusieurs rôles avec role:...', function () {
        Route::middleware(['api', 'auth:sanctum', 'role:client,prestataire'])->get('/api/test-roles', fn () => response()->json(['ok' => true]));

        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/test-roles')->assertOk();
        $this->actingAs(prestataireCatalogue()->user, 'sanctum')->getJson('/api/test-roles')->assertOk();
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')->getJson('/api/test-roles')->assertForbidden();
    });
});
