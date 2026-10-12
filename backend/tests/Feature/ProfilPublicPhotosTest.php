<?php

use App\Enums\StatutCompte;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const URL_PHOTOS = '/api/v1/prestataire/photos';

beforeEach(function () {
    Storage::fake('local');
});

function prestataireProfil(StatutCompte $statut = StatutCompte::Actif, array $attributs = []): Prestataire
{
    return Prestataire::factory()->for(User::factory()->prestataire()->statut($statut), 'user')->create($attributs);
}

function photoWebp(): UploadedFile
{
    $chemin = tempnam(sys_get_temp_dir(), 'sopi');
    file_put_contents($chemin, 'RIFF'.pack('V', 12).'WEBPVP8 '.str_repeat("\0", 16));

    return new UploadedFile($chemin, 'atelier.webp', 'image/webp', null, true);
}

function envoyerPhoto(?UploadedFile $fichier = null)
{
    return test()->postJson(URL_PHOTOS, ['photo' => $fichier ?? fichierJustificatif('png', 'atelier.png')]);
}

it('refuse sans jeton', function () {
    $this->getJson(URL_PHOTOS)->assertUnauthorized();
    $this->postJson(URL_PHOTOS)->assertUnauthorized();
    $this->deleteJson(URL_PHOTOS.'/'.fake()->uuid())->assertUnauthorized();

    $id = fake()->uuid();
    $this->getJson("/api/v1/prestataires/$id")->assertUnauthorized();
    $this->getJson("/api/v1/prestataires/$id/photos/".fake()->uuid())->assertUnauthorized();
});

it('refuse les mauvais rôles', function () {
    $id = fake()->uuid();

    foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
        Sanctum::actingAs($user);
        $this->getJson(URL_PHOTOS)->assertForbidden();
        $this->postJson(URL_PHOTOS)->assertForbidden();
        $this->deleteJson(URL_PHOTOS.'/'.fake()->uuid())->assertForbidden();
    }

    foreach ([User::factory()->prestataire()->create(), User::factory()->administrateur()->create()] as $user) {
        Sanctum::actingAs($user);
        $this->getJson("/api/v1/prestataires/$id")->assertForbidden();
        $this->getJson("/api/v1/prestataires/$id/photos/".fake()->uuid())->assertForbidden();
    }
});

describe('photos du prestataire', function () {
    it('ajoute une photo JPG, PNG ou WebP sous un nom UUID dans le dossier du prestataire', function () {
        $prestataire = prestataireProfil();
        Sanctum::actingAs($prestataire->user);

        $ids = [];
        foreach ([fichierJustificatif('jpg', 'a.jpg'), fichierJustificatif('png', 'b.png'), photoWebp()] as $fichier) {
            $reponse = envoyerPhoto($fichier)->assertCreated();
            expect($reponse->json('id'))->toBeUuid();
            $ids[] = $reponse->json('id');
        }

        $fichiers = Storage::disk('local')->files("photos/{$prestataire->id}");
        $enregistre = $prestataire->fresh()->photos_atelier;

        expect($fichiers)->toHaveCount(3)
            ->and($enregistre)->toHaveCount(3)
            ->and(collect($enregistre)->map(fn ($n) => pathinfo($n, PATHINFO_FILENAME))->all())->toBe($ids)
            ->and(collect($enregistre)->map(fn ($n) => pathinfo($n, PATHINFO_EXTENSION))->all())->toBe(['jpg', 'png', 'webp'])
            ->and(collect($enregistre)->contains(fn ($n) => str_contains($n, '/')))->toBeFalse();
    });

    it('liste mes photos', function () {
        $prestataire = prestataireProfil();
        Sanctum::actingAs($prestataire->user);
        $a = envoyerPhoto()->json('id');
        $b = envoyerPhoto()->json('id');

        $this->getJson(URL_PHOTOS)->assertOk()->assertExactJson(['data' => [['id' => $a, 'url' => URL_PHOTOS."/$a"], ['id' => $b, 'url' => URL_PHOTOS."/$b"]]]);
    });

    it('ne liste pas les photos des autres', function () {
        $autre = prestataireProfil();
        Sanctum::actingAs($autre->user);
        envoyerPhoto()->assertCreated();

        Sanctum::actingAs(prestataireProfil()->user);
        $this->getJson(URL_PHOTOS)->assertOk()->assertExactJson(['data' => []]);
    });

    it('refuse les fichiers invalides en 422 français', function (string $type, string $nom, ?int $taille, string $message) {
        $prestataire = prestataireProfil();
        Sanctum::actingAs($prestataire->user);

        envoyerPhoto(fichierJustificatif($type, $nom, $taille))
            ->assertUnprocessable()
            ->assertJsonPath('errors.photo.0', $message);

        expect(Storage::disk('local')->allFiles())->toBe([])
            ->and($prestataire->fresh()->photos_atelier)->toBe([]);
    })->with([
        'texte' => ['texte', 'notes.txt', null, 'La photo doit être une image JPG, PNG ou WebP.'],
        'pdf' => ['pdf', 'plan.pdf', null, 'La photo doit être une image JPG, PNG ou WebP.'],
        'texte déguisé en jpg' => ['texte', 'faux.jpg', null, 'La photo doit être une image JPG, PNG ou WebP.'],
        'pdf déguisé en png' => ['pdf', 'faux.png', null, 'La photo doit être une image JPG, PNG ou WebP.'],
        'trop gros' => ['png', 'gros.png', 6 * 1024 * 1024, 'La photo ne doit pas dépasser 5 Mo.'],
    ]);

    it('refuse l\'absence de photo', function () {
        Sanctum::actingAs(prestataireProfil()->user);

        $this->postJson(URL_PHOTOS)->assertUnprocessable()->assertJsonPath('errors.photo.0', 'Veuillez joindre une photo.');
    });

    it('refuse la 7e photo', function () {
        $prestataire = prestataireProfil();
        Sanctum::actingAs($prestataire->user);

        foreach (range(1, 6) as $i) {
            envoyerPhoto()->assertCreated();
        }

        envoyerPhoto()->assertUnprocessable()
            ->assertJsonPath('message', 'Vous ne pouvez pas ajouter plus de 6 photos.')
            ->assertJsonPath('errors.photo.0', 'Vous ne pouvez pas ajouter plus de 6 photos.');

        expect(Storage::disk('local')->files("photos/{$prestataire->id}"))->toHaveCount(6)
            ->and($prestataire->fresh()->photos_atelier)->toHaveCount(6);
    });

    it('supprime le fichier et l\'entrée', function () {
        $prestataire = prestataireProfil();
        Sanctum::actingAs($prestataire->user);
        $garde = envoyerPhoto()->json('id');
        $supprimee = envoyerPhoto()->json('id');

        $this->deleteJson(URL_PHOTOS."/$supprimee")->assertNoContent();

        expect(Storage::disk('local')->files("photos/{$prestataire->id}"))->toHaveCount(1)
            ->and($prestataire->fresh()->photos_atelier)->toHaveCount(1)
            ->and(pathinfo($prestataire->fresh()->photos_atelier[0], PATHINFO_FILENAME))->toBe($garde);

        $this->deleteJson(URL_PHOTOS."/$supprimee")->assertNotFound()->assertJsonPath('message', 'Photo introuvable.');
    });

    it('répond 404 pour la photo d\'un autre prestataire sans la supprimer', function () {
        $autre = prestataireProfil();
        Sanctum::actingAs($autre->user);
        $idAutre = envoyerPhoto()->json('id');

        $moi = prestataireProfil();
        Sanctum::actingAs($moi->user);

        $this->deleteJson(URL_PHOTOS."/$idAutre")->assertNotFound();
        $this->deleteJson(URL_PHOTOS.'/'.fake()->uuid())->assertNotFound();

        expect($autre->fresh()->photos_atelier)->toHaveCount(1)
            ->and(Storage::disk('local')->files("photos/{$autre->id}"))->toHaveCount(1);
    });
});

describe('profil public', function () {
    function profilPublic(string $id)
    {
        return test()->getJson("/api/v1/prestataires/$id");
    }

    it('renvoie le profil avec les seules prestations actives et les photos', function () {
        $prestataire = prestataireProfil(StatutCompte::Actif, ['nom_entreprise' => 'Garage Moussa', 'quartier' => 'Malika', 'adresse' => 'Rue 10']);
        $active = Prestation::factory()->create(['prestataire_id' => $prestataire->id, 'nom' => 'Vidange', 'prix' => 15000, 'duree_minutes' => 60]);
        Prestation::factory()->inactive()->create(['prestataire_id' => $prestataire->id, 'nom' => 'Cachée']);

        Sanctum::actingAs($prestataire->user);
        $photo = envoyerPhoto()->json('id');
        Sanctum::actingAs(User::factory()->create());

        profilPublic($prestataire->id)->assertOk()->assertExactJson([
            'id' => $prestataire->id,
            'nomEntreprise' => 'Garage Moussa',
            'metier' => $prestataire->metier->value,
            'quartier' => 'Malika',
            'adresse' => 'Rue 10',
            'prestations' => [[
                'id' => $active->id,
                'nom' => 'Vidange',
                'domaine' => $prestataire->metier->value,
                'dureeMinutes' => 60,
                'prix' => 15000,
            ]],
            'photos' => [[
                'id' => $photo,
                'url' => "/api/v1/prestataires/{$prestataire->id}/photos/$photo",
            ]],
        ]);
    });

    it('répond 404 pour un compte non actif, un inconnu ou un identifiant invalide', function (?StatutCompte $statut) {
        Sanctum::actingAs(User::factory()->create());
        $id = $statut ? prestataireProfil($statut)->id : fake()->uuid();

        profilPublic($id)->assertNotFound()->assertJsonPath('message', 'Prestataire introuvable.');
    })->with([
        'en attente' => [StatutCompte::EnAttenteValidation],
        'refusé' => [StatutCompte::Refuse],
        'suspendu' => [StatutCompte::Suspendu],
        'banni' => [StatutCompte::Banni],
        'inexistant' => [null],
    ]);

    it('répond 404 pour un identifiant qui n\'est pas un UUID', function () {
        Sanctum::actingAs(User::factory()->create());

        profilPublic('pas-un-uuid')->assertNotFound()->assertJsonPath('message', 'Prestataire introuvable.');
    });

    it('ne contient aucune donnée personnelle ni chemin de stockage', function () {
        $prestataire = prestataireProfil();
        Prestation::factory()->create(['prestataire_id' => $prestataire->id]);
        Sanctum::actingAs($prestataire->user);
        envoyerPhoto()->assertCreated();
        $user = $prestataire->user;

        Sanctum::actingAs(User::factory()->create());
        $contenu = profilPublic($prestataire->id)->assertOk()->getContent();

        expect($contenu)->not->toContain($user->telephone)
            ->and($contenu)->not->toContain((string) $user->email)
            ->and($contenu)->not->toContain($user->nom)
            ->and($contenu)->not->toContain($user->id)
            ->and($contenu)->not->toContain('photos/')
            ->and($contenu)->not->toContain('storage')
            ->and($contenu)->not->toContain('photos_atelier')
            ->and($contenu)->not->toContain('user');

        $liste = collect(array_keys(json_decode($contenu, true)))->all();
        expect($liste)->toBe(['id', 'nomEntreprise', 'metier', 'quartier', 'adresse', 'prestations', 'photos']);
    });

    it('ne contient aucune donnée personnelle dans la liste de mes photos', function () {
        $prestataire = prestataireProfil();
        Sanctum::actingAs($prestataire->user);
        envoyerPhoto()->assertCreated();

        $contenu = $this->getJson(URL_PHOTOS)->getContent();

        expect($contenu)->not->toContain('photos/')->and($contenu)->not->toContain($prestataire->id);
    });
});

describe('téléchargement d\'une photo par un client', function () {
    it('renvoie l\'image avec les bons en-têtes', function () {
        $prestataire = prestataireProfil();
        Sanctum::actingAs($prestataire->user);
        $fichier = fichierJustificatif('png', 'atelier.png');
        $octets = file_get_contents($fichier->getPathname());
        $id = envoyerPhoto($fichier)->json('id');

        Sanctum::actingAs(User::factory()->create());
        $reponse = $this->get("/api/v1/prestataires/{$prestataire->id}/photos/$id", ['Accept' => 'image/png']);

        $reponse->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($reponse->headers->get('Cache-Control'))->toContain('private')
            ->and($reponse->headers->get('Content-Disposition'))->toStartWith('inline')
            ->and($reponse->streamedContent())->toBe($octets);
    });

    it('répond 404 pour une photo inconnue, supprimée ou d\'un autre prestataire', function () {
        $a = prestataireProfil();
        $b = prestataireProfil();
        Sanctum::actingAs($a->user);
        $idA = envoyerPhoto()->json('id');
        $supprimee = envoyerPhoto()->json('id');
        $this->deleteJson(URL_PHOTOS."/$supprimee")->assertNoContent();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/prestataires/{$a->id}/photos/".fake()->uuid())->assertNotFound();
        $this->getJson("/api/v1/prestataires/{$a->id}/photos/$supprimee")->assertNotFound();
        $this->getJson("/api/v1/prestataires/{$b->id}/photos/$idA")->assertNotFound();
        $this->getJson('/api/v1/prestataires/'.fake()->uuid()."/photos/$idA")->assertNotFound();
    });

    it('répond 404 si le compte du prestataire n\'est pas actif', function (StatutCompte $statut) {
        $prestataire = prestataireProfil();
        Sanctum::actingAs($prestataire->user);
        $id = envoyerPhoto()->json('id');

        $prestataire->user->forceFill(['statut' => $statut])->save();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/prestataires/{$prestataire->id}/photos/$id")->assertNotFound();
    })->with([
        'suspendu' => [StatutCompte::Suspendu],
        'banni' => [StatutCompte::Banni],
    ]);
});
