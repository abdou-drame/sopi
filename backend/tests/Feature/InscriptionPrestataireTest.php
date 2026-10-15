<?php

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\Justificatif;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const URL_INSCRIPTION_PRESTATAIRE = '/api/v1/prestataires/inscription';

beforeEach(function () {
    Storage::fake('local');
});

/** Vrai fichier temporaire (le type est lu dans le contenu, pas dans le nom). */
function fichierJustificatif(string $type = 'pdf', ?string $nom = null, ?int $tailleOctets = null): UploadedFile
{
    $contenus = [
        'pdf' => "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
        'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='),
        'jpg' => base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='),
        'texte' => "Ceci est un simple texte.\n",
    ];
    $contenu = $contenus[$type];
    if ($tailleOctets !== null) {
        $contenu = str_pad($contenu, $tailleOctets, ' ');
    }

    $chemin = tempnam(sys_get_temp_dir(), 'sopi');
    file_put_contents($chemin, $contenu);
    $mimes = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'texte' => 'text/plain'];

    return new UploadedFile($chemin, $nom ?? "piece.$type", $mimes[$type], null, true);
}

function donneesDossierPrestataire(array $surcharge = []): array
{
    return array_merge([
        'nom' => 'Diop',
        'prenom' => 'Moussa',
        'telephone' => '77 123 45 67',
        'email' => 'moussa@example.com',
        'mot_de_passe' => 'mot-de-passe-exemple',
        'mot_de_passe_confirmation' => 'mot-de-passe-exemple',
        'nom_entreprise' => 'Garage Moussa',
        'metier' => 'mecanique',
        'quartier' => 'Malika',
        'adresse' => 'Rue 10, près du marché',
        'justificatifs' => [
            ['type_document' => 'cni', 'fichier' => fichierJustificatif('png', 'ma-cni.png')],
            ['type_document' => 'diplome', 'fichier' => fichierJustificatif('pdf', 'mon-diplome.pdf')],
        ],
    ], $surcharge);
}

function fichiersJustificatifsStockes(): array
{
    return Storage::disk('local')->allFiles('justificatifs');
}

describe('POST /prestataires/inscription', function () {
    it('inscrit un prestataire avec son dossier complet', function () {
        $reponse = $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire())
            ->assertCreated()
            ->assertJsonStructure([
                'id', 'nom', 'prenom', 'nomEntreprise', 'metier', 'statutCompte', 'jeton_suivi', 'lien_suivi',
                'justificatifs' => ['*' => ['type', 'statut']],
            ])
            ->assertJsonPath('nom', 'Diop')
            ->assertJsonPath('nomEntreprise', 'Garage Moussa')
            ->assertJsonPath('metier', 'mecanique')
            ->assertJsonPath('statutCompte', 'en_attente')
            ->assertJsonPath('justificatifs.0', ['type' => 'cni', 'statut' => 'en_attente'])
            ->assertJsonPath('justificatifs.1', ['type' => 'diplome', 'statut' => 'en_attente']);

        $prestataire = Prestataire::with('user', 'justificatifs')->findOrFail($reponse->json('id'));
        expect($prestataire->user->role)->toBe(Role::Prestataire)
            ->and($prestataire->user->statut)->toBe(StatutCompte::EnAttenteValidation)
            ->and($prestataire->user->telephone)->toBe('+221771234567')
            ->and((float) $prestataire->solde)->toBe(0.0)
            ->and($prestataire->justificatifs)->toHaveCount(2);

        expect(fichiersJustificatifsStockes())->toHaveCount(2);
        foreach ($prestataire->justificatifs as $justificatif) {
            expect($justificatif->chemin_fichier)->toStartWith("justificatifs/{$prestataire->id}/")
                ->and($justificatif->chemin_fichier)->toMatch('#/[0-9a-f-]{36}\.(pdf|png|jpg)$#')
                ->and($justificatif->chemin_fichier)->not->toContain('ma-cni')
                ->and($justificatif->chemin_fichier)->not->toContain('mon-diplome');
            Storage::disk('local')->assertExists($justificatif->chemin_fichier);
        }
    });

    it('accepte une attestation comme justificatif professionnel et des JPG', function () {
        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire([
            'justificatifs' => [
                ['type_document' => 'cni', 'fichier' => fichierJustificatif('jpg')],
                ['type_document' => 'attestation', 'fichier' => fichierJustificatif('pdf')],
            ],
        ]))->assertCreated();
    });

    it('refuse un dossier sans CNI', function () {
        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire([
            'justificatifs' => [
                ['type_document' => 'diplome', 'fichier' => fichierJustificatif('pdf')],
                ['type_document' => 'attestation', 'fichier' => fichierJustificatif('pdf')],
            ],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('justificatifs')
            ->assertJsonPath('errors.justificatifs.0', 'Le dossier doit contenir une copie de votre CNI.');

        expect(User::count())->toBe(0)->and(fichiersJustificatifsStockes())->toBeEmpty();
    });

    it('refuse un dossier sans justificatif professionnel', function () {
        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire([
            'justificatifs' => [
                ['type_document' => 'cni', 'fichier' => fichierJustificatif('png')],
                ['type_document' => 'cni', 'fichier' => fichierJustificatif('pdf')],
            ],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('justificatifs');

        expect(User::count())->toBe(0)->and(fichiersJustificatifsStockes())->toBeEmpty();
    });

    it('refuse plus de 5 fichiers', function () {
        $pieces = [['type_document' => 'cni', 'fichier' => fichierJustificatif('png')]];
        foreach (range(1, 5) as $i) {
            $pieces[] = ['type_document' => 'diplome', 'fichier' => fichierJustificatif('pdf')];
        }

        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire(['justificatifs' => $pieces]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('justificatifs');
    });

    it('refuse un fichier de plus de 5 Mo', function () {
        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire([
            'justificatifs' => [
                ['type_document' => 'cni', 'fichier' => fichierJustificatif('pdf', 'gros.pdf', 5 * 1024 * 1024 + 1024)],
                ['type_document' => 'diplome', 'fichier' => fichierJustificatif('pdf')],
            ],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('justificatifs.0.fichier');

        expect(User::count())->toBe(0)->and(fichiersJustificatifsStockes())->toBeEmpty();
    });

    it('refuse un fichier du mauvais type, même avec une extension .pdf', function () {
        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire([
            'justificatifs' => [
                ['type_document' => 'cni', 'fichier' => fichierJustificatif('texte', 'faux.pdf')],
                ['type_document' => 'diplome', 'fichier' => fichierJustificatif('pdf')],
            ],
        ]))->assertUnprocessable()
            ->assertJsonPath('errors', fn ($erreurs) => in_array(
                'Veuillez joindre un fichier PDF ou image valide',
                $erreurs['justificatifs.0.fichier'] ?? [],
            ));

        expect(User::count())->toBe(0)->and(fichiersJustificatifsStockes())->toBeEmpty();
    });

    it('refuse un téléphone déjà utilisé', function () {
        User::factory()->create(['telephone' => '+221771234567']);

        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire())
            ->assertUnprocessable()
            ->assertJsonPath('errors.telephone.0', 'Ce numéro de téléphone est déjà utilisé.');
    });

    it('refuse un e-mail déjà utilisé', function () {
        User::factory()->create(['email' => 'moussa@example.com']);

        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire())
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Cette adresse e-mail est déjà utilisée.');
    });

    it('refuse un métier invalide', function () {
        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire(['metier' => 'plomberie']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metier');
    });

    it('refuse un mot de passe trop court ou non confirmé', function () {
        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire([
            'mot_de_passe' => 'court', 'mot_de_passe_confirmation' => 'court',
        ]))->assertUnprocessable()->assertJsonValidationErrors('mot_de_passe');

        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire(['mot_de_passe_confirmation' => 'autre-chose-123']))
            ->assertUnprocessable()->assertJsonValidationErrors('mot_de_passe');
    });

    it('annule tout (base et fichiers) quand une étape échoue', function () {
        $appels = 0;
        Justificatif::creating(function () use (&$appels) {
            if (++$appels === 2) {
                throw new RuntimeException('Panne simulée');
            }
        });

        $this->withoutExceptionHandling();
        expect(fn () => $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire()))
            ->toThrow(RuntimeException::class, 'Panne simulée');

        expect(User::count())->toBe(0)
            ->and(Prestataire::count())->toBe(0)
            ->and(Justificatif::count())->toBe(0)
            ->and(fichiersJustificatifsStockes())->toBeEmpty();
    });

    it('ne stocke que le hachage du jeton de suivi', function () {
        $reponse = $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire())->assertCreated();

        $jeton = $reponse->json('jeton_suivi');
        $prestataire = Prestataire::findOrFail($reponse->json('id'));

        expect(strlen($jeton))->toBeGreaterThanOrEqual(40)
            ->and($prestataire->jeton_suivi_hash)->not->toBe($jeton)
            ->and($prestataire->jeton_suivi_hash)->toBe(hash('sha256', $jeton))
            ->and($reponse->json('lien_suivi'))->toBe(rtrim(config('app.url'), '/').'/suivi/'.$jeton);
        $this->assertDatabaseMissing('prestataires', ['jeton_suivi_hash' => $jeton]);
    });

    it('ne renvoie jamais le chemin des fichiers ni le hachage', function () {
        $reponse = $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire())->assertCreated();
        $brut = $reponse->getContent();
        $prestataire = Prestataire::with('justificatifs')->findOrFail($reponse->json('id'));

        expect($brut)->not->toContain('chemin')
            ->and($brut)->not->toContain('justificatifs/')
            ->and($brut)->not->toContain($prestataire->jeton_suivi_hash);
        foreach ($prestataire->justificatifs as $justificatif) {
            expect($brut)->not->toContain($justificatif->chemin_fichier);
        }
    });

    it("enregistre le nom d'origine nettoyé, le type et la taille de chaque justificatif", function () {
        $reponse = $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire([
            'justificatifs' => [
                ['type_document' => 'cni', 'fichier' => fichierJustificatif('png', '..\\..\\ma-cni.png')],
                ['type_document' => 'diplome', 'fichier' => fichierJustificatif('pdf', 'diplôme.pdf')],
            ],
        ]))->assertCreated();

        $pieces = Justificatif::where('prestataire_id', $reponse->json('id'))->get()->keyBy('type_document');
        expect($pieces['cni']->nom_original)->toBe('ma-cni.png')
            ->and($pieces['cni']->type_mime)->toBe('image/png')
            ->and($pieces['diplome']->nom_original)->toBe('diplôme.pdf')
            ->and($pieces['diplome']->type_mime)->toBe('application/pdf')
            ->and($pieces['diplome']->taille)->toBeGreaterThan(0);
    });

    it("empêche la connexion d'un compte en attente de validation", function () {
        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, donneesDossierPrestataire())->assertCreated();

        $this->postJson('/api/v1/auth/connexion', [
            'telephone' => '77 123 45 67', 'password' => 'mot-de-passe-exemple',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Votre compte est en attente de validation.')
            ->assertJsonMissingPath('token');
    });

    it('limite à 10 requêtes par heure et par IP', function () {
        foreach (range(1, 10) as $i) {
            $this->postJson(URL_INSCRIPTION_PRESTATAIRE, [])->assertUnprocessable();
        }

        $this->postJson(URL_INSCRIPTION_PRESTATAIRE, [])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Trop de tentatives. Réessayez dans quelques instants.');
    });
});
