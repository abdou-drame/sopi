<?php

use App\Models\Metier;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const URL_METIERS = '/api/v1/admin/metiers';

/** Dossier d'inscription minimal pour le métier donné (pièces factices : un PNG et un PDF). */
function dossierPourMetier(array $surcharge = []): array
{
    $png = tempnam(sys_get_temp_dir(), 'sopi');
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $pdf = tempnam(sys_get_temp_dir(), 'sopi');
    file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

    return array_merge([
        'nom' => 'Diop',
        'prenom' => 'Moussa',
        'telephone' => '77 123 45 67',
        'email' => 'moussa@example.com',
        'mot_de_passe' => 'mot-de-passe-exemple',
        'mot_de_passe_confirmation' => 'mot-de-passe-exemple',
        'nom_entreprise' => 'Atelier Moussa',
        'metier' => 'mecanique',
        'quartier' => 'Malika',
        'adresse' => 'Rue 10, près du marché',
        'justificatifs' => [
            ['type_document' => 'cni', 'fichier' => new UploadedFile($png, 'cni.png', 'image/png', null, true)],
            ['type_document' => 'diplome', 'fichier' => new UploadedFile($pdf, 'diplome.pdf', 'application/pdf', null, true)],
        ],
    ], $surcharge);
}

describe('métiers — accès', function () {
    it('exige une authentification', function () {
        $this->getJson(URL_METIERS)->assertUnauthorized();
        $this->postJson(URL_METIERS, ['libelle' => 'Plomberie'])->assertUnauthorized();
        $this->patchJson(URL_METIERS.'/'.Metier::first()->id, ['actif' => false])->assertUnauthorized();
    });

    it('refuse un client et un prestataire (403)', function () {
        $id = Metier::first()->id;
        foreach ([User::factory()->create(), User::factory()->prestataire()->create()] as $intrus) {
            $this->actingAs($intrus, 'sanctum');
            $this->getJson(URL_METIERS)->assertForbidden();
            $this->postJson(URL_METIERS, ['libelle' => 'Plomberie'])->assertForbidden();
            $this->patchJson(URL_METIERS.'/'.$id, ['actif' => false])->assertForbidden();
        }
        expect(Metier::where('code', 'plomberie')->exists())->toBeFalse();
    });
});

describe('métiers — liste', function () {
    it('contient les trois métiers de départ, actifs, par libellé', function () {
        adminConnecte();

        $reponse = $this->getJson(URL_METIERS)->assertOk();

        expect($reponse->json('data.*.code'))->toBe(['coiffure', 'mecanique', 'pressing']);
        expect($reponse->json('data.*.actif'))->toBe([true, true, true]);
        $reponse->assertJsonStructure(['data' => [['id', 'code', 'libelle', 'actif', 'nombrePrestataires', 'dateCreation']]]);
    });

    it('compte les prestataires rattachés à chaque métier', function () {
        adminConnecte();
        Prestataire::factory()->count(2)->metier('coiffure')->create();
        Prestataire::factory()->metier('pressing')->create();

        $comptes = collect($this->getJson(URL_METIERS)->json('data'))->pluck('nombrePrestataires', 'code')->all();

        expect($comptes)->toBe(['coiffure' => 2, 'mecanique' => 0, 'pressing' => 1]);
    });
});

describe('métiers — création', function () {
    it('crée un métier actif dont le code est déduit du nom', function () {
        adminConnecte();

        $this->postJson(URL_METIERS, ['libelle' => '  Électricité   auto '])
            ->assertCreated()
            ->assertJsonPath('code', 'electriciteauto')
            ->assertJsonPath('libelle', 'Électricité auto')
            ->assertJsonPath('actif', true)
            ->assertJsonPath('nombrePrestataires', 0);

        expect(Metier::where('code', 'electriciteauto')->exists())->toBeTrue();
    });

    it('refuse un nom déjà pris, même écrit autrement (422)', function () {
        adminConnecte();

        $this->postJson(URL_METIERS, ['libelle' => 'MÉCANIQUE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('libelle');

        expect(Metier::count())->toBe(3);
    });

    it('refuse un nom absent, trop court, trop long ou sans lettre (422)', function () {
        adminConnecte();

        foreach ([[], ['libelle' => 'A'], ['libelle' => str_repeat('a', 61)], ['libelle' => '!!!']] as $corps) {
            $this->postJson(URL_METIERS, $corps)->assertUnprocessable()->assertJsonValidationErrors('libelle');
        }
    });
});

describe('métiers — modification', function () {
    it('renomme un métier sans changer son code', function () {
        adminConnecte();
        $metier = Metier::where('code', 'pressing')->first();

        $this->patchJson(URL_METIERS.'/'.$metier->id, ['libelle' => 'Pressing et repassage'])
            ->assertOk()
            ->assertJsonPath('libelle', 'Pressing et repassage')
            ->assertJsonPath('code', 'pressing');
    });

    it('refuse un nom qui correspond à un autre métier (422) mais accepte celui du métier lui-même', function () {
        adminConnecte();
        $metier = Metier::where('code', 'pressing')->first();

        $this->patchJson(URL_METIERS.'/'.$metier->id, ['libelle' => 'Coiffure'])->assertUnprocessable()->assertJsonValidationErrors('libelle');
        $this->patchJson(URL_METIERS.'/'.$metier->id, ['libelle' => 'PRESSING'])->assertOk();
    });

    it('désactive puis réactive un métier', function () {
        adminConnecte();
        $metier = Metier::where('code', 'pressing')->first();

        $this->patchJson(URL_METIERS.'/'.$metier->id, ['actif' => false])->assertOk()->assertJsonPath('actif', false);
        expect($metier->fresh()->actif)->toBeFalse();

        $this->patchJson(URL_METIERS.'/'.$metier->id, ['actif' => true])->assertOk()->assertJsonPath('actif', true);
    });

    it('refuse de désactiver le dernier métier actif (409)', function () {
        adminConnecte();
        Metier::whereIn('code', ['coiffure', 'pressing'])->update(['actif' => false]);
        $dernier = Metier::where('code', 'mecanique')->first();

        $this->patchJson(URL_METIERS.'/'.$dernier->id, ['actif' => false])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Au moins un métier doit rester actif.');

        expect($dernier->fresh()->actif)->toBeTrue();
    });

    it('refuse une requête vide (422) et un métier inconnu (404)', function () {
        adminConnecte();

        $this->patchJson(URL_METIERS.'/'.Metier::first()->id, [])->assertUnprocessable();
        $this->patchJson(URL_METIERS.'/0198a1b2-0000-7000-8000-0000000000ff', ['actif' => false])
            ->assertNotFound()
            ->assertJsonPath('message', 'Métier introuvable.');
        $this->patchJson(URL_METIERS.'/pas-un-uuid', ['actif' => false])->assertNotFound();
    });
});

describe('métiers — effets sur le reste de la plateforme', function () {
    beforeEach(fn () => Storage::fake('local'));

    it('propose un nouveau métier à l\'inscription et à la recherche', function () {
        adminConnecte();
        $this->postJson(URL_METIERS, ['libelle' => 'Plomberie'])->assertCreated();

        $this->postJson('/api/v1/prestataires/inscription', dossierPourMetier(['metier' => 'plomberie']))
            ->assertCreated()
            ->assertJsonPath('metier', 'plomberie');

        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/prestataires?metier=plomberie')->assertOk();
    });

    it('refuse l\'inscription sur un métier désactivé, mais garde ses prestataires', function () {
        $existant = Prestataire::factory()->metier('pressing')->create();
        Metier::where('code', 'pressing')->update(['actif' => false]);

        $this->postJson('/api/v1/prestataires/inscription', dossierPourMetier(['metier' => 'pressing']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metier');

        expect($existant->fresh()->metier)->toBe('pressing');
    });
});
