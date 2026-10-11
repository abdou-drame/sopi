<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

describe('GET /me', function () {
    it('renvoie le profil sans mot de passe', function (string $role) {
        $user = User::factory()->state(['role' => Role::from($role)])->create(['nom' => 'Diop', 'prenom' => 'Awa']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me')
            ->assertOk()
            ->assertExactJsonStructure(['id', 'nom', 'prenom', 'email', 'telephone', 'role', 'statutCompte', 'dateCreation'])
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('role', $user->role->value)
            ->assertJsonPath('statutCompte', 'actif')
            ->assertJsonMissingPath('password');
    })->with(['client', 'prestataire', 'administrateur']);

    it('renvoie 401 sans jeton', function () {
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonStructure(['message']);
    });
});

describe('PUT /me', function () {
    it('modifie nom, prénom et téléphone (normalisé)', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/me', [
            'nom' => 'Ndiaye', 'prenom' => 'Moussa', 'telephone' => '76 111 22 33',
        ])->assertOk()
            ->assertJsonPath('nom', 'Ndiaye')
            ->assertJsonPath('prenom', 'Moussa')
            ->assertJsonPath('telephone', '+221761112233');

        expect($user->fresh()->telephone)->toBe('+221761112233');
    });

    it('accepte le numéro de l\'utilisateur lui-même', function () {
        $user = User::factory()->create(['telephone' => '+221771234567']);

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/me', [
            'nom' => 'A', 'prenom' => 'B', 'telephone' => '771234567',
        ])->assertOk();
    });

    it('ne modifie ni l\'e-mail ni le rôle ni le statut', function () {
        $user = User::factory()->create(['email' => 'avant@example.com']);

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/me', [
            'nom' => 'Ndiaye', 'prenom' => 'Moussa', 'telephone' => $user->telephone,
            'email' => 'apres@example.com', 'role' => 'administrateur', 'statut' => 'banni',
        ])->assertOk()
            ->assertJsonPath('email', 'avant@example.com')
            ->assertJsonPath('role', 'client');

        $user->refresh();
        expect($user->email)->toBe('avant@example.com')
            ->and($user->role)->toBe(Role::Client)
            ->and($user->statut->value)->toBe('actif');
    });

    it('renvoie 401 sans jeton', function () {
        $this->putJson('/api/v1/me', [])->assertUnauthorized();
    });

    it('renvoie 422 en français quand les champs sont absents', function () {
        $this->actingAs(User::factory()->create(), 'sanctum')->putJson('/api/v1/me', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['nom', 'prenom', 'telephone']])
            ->assertJsonPath('errors.nom.0', 'Le champ nom est obligatoire.');
    });

    it('refuse un téléphone qui n\'est pas un mobile sénégalais', function (string $numero) {
        $this->actingAs(User::factory()->create(), 'sanctum')->putJson('/api/v1/me', [
            'nom' => 'A', 'prenom' => 'B', 'telephone' => $numero,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.telephone.0', 'Le numéro de téléphone doit être un mobile sénégalais valide (ex. : 77 123 45 67).');
    })->with(['12345', '331234567', '77123456', '+33612345678']);

    it('refuse un téléphone déjà utilisé par un autre compte', function () {
        User::factory()->create(['telephone' => '+221761112233']);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/me', [
            'nom' => 'A', 'prenom' => 'B', 'telephone' => '76 111 22 33',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.telephone.0', 'Ce numéro de téléphone est déjà utilisé.');
    });
});

describe('PUT /me/mot-de-passe', function () {
    function donneesMotDePasse(array $surcharge = []): array
    {
        return array_merge([
            'mot_de_passe_actuel' => 'motdepasse',
            'nouveau_mot_de_passe' => 'nouveau-mot-de-passe',
            'confirmation' => 'nouveau-mot-de-passe',
        ], $surcharge);
    }

    it('change le mot de passe, révoque les autres jetons et garde le jeton courant', function () {
        $user = User::factory()->create(); // mot de passe de la factory : « motdepasse »
        $courant = $user->createToken('courant')->plainTextToken;
        $user->createToken('autre-appareil');
        $user->createToken('autre-appareil-2');
        expect($user->tokens()->count())->toBe(3);

        $this->withToken($courant)->putJson('/api/v1/me/mot-de-passe', donneesMotDePasse())
            ->assertOk()
            ->assertJsonPath('message', 'Votre mot de passe a été modifié.');

        expect(Hash::check('nouveau-mot-de-passe', $user->fresh()->password))->toBeTrue()
            ->and($user->tokens()->count())->toBe(1)
            ->and($user->tokens()->first()->id)->toBe(PersonalAccessToken::findToken($courant)->id);

        // Le jeton courant fonctionne toujours.
        $this->withToken($courant)->getJson('/api/v1/me')->assertOk();
    });

    it('permet de se connecter avec le nouveau mot de passe uniquement', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/mot-de-passe', donneesMotDePasse())->assertOk();

        $this->postJson('/api/v1/auth/connexion', ['telephone' => $user->telephone, 'password' => 'nouveau-mot-de-passe'])->assertOk();
        $this->postJson('/api/v1/auth/connexion', ['telephone' => $user->telephone, 'password' => 'motdepasse'])->assertUnauthorized();
    });

    it('refuse un mot de passe actuel faux, sans rien modifier ni révoquer', function () {
        $user = User::factory()->create();
        $courant = $user->createToken('courant')->plainTextToken;
        $user->createToken('autre');
        $ancienneEmpreinte = $user->password;

        $this->withToken($courant)->putJson('/api/v1/me/mot-de-passe', donneesMotDePasse(['mot_de_passe_actuel' => 'faux-faux-faux']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.mot_de_passe_actuel.0', 'Le mot de passe actuel est incorrect.');

        expect($user->fresh()->password)->toBe($ancienneEmpreinte)
            ->and($user->tokens()->count())->toBe(2);
    });

    it('refuse un nouveau mot de passe de moins de 8 caractères', function () {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/v1/me/mot-de-passe', donneesMotDePasse(['nouveau_mot_de_passe' => 'court', 'confirmation' => 'court']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.nouveau_mot_de_passe.0', 'Le champ nouveau mot de passe doit contenir au moins 8 caractères.');
    });

    it('refuse une confirmation différente', function () {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/v1/me/mot-de-passe', donneesMotDePasse(['confirmation' => 'autre-chose-123']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.confirmation.0', 'La confirmation ne correspond pas au nouveau mot de passe.');
    });

    it('renvoie 422 quand les champs sont absents', function () {
        $this->actingAs(User::factory()->create(), 'sanctum')->putJson('/api/v1/me/mot-de-passe', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['mot_de_passe_actuel', 'nouveau_mot_de_passe', 'confirmation']]);
    });

    it('renvoie 401 sans jeton', function () {
        $this->putJson('/api/v1/me/mot-de-passe', donneesMotDePasse())->assertUnauthorized();
    });
});

describe('PUT /me/jeton-push', function () {
    it('enregistre le jeton push', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/jeton-push', ['jetonPush' => 'jeton-fcm-fictif-123'])
            ->assertOk()
            ->assertJsonPath('message', 'Jeton de notification enregistré.');

        expect($user->fresh()->jeton_push)->toBe('jeton-fcm-fictif-123');
    });

    it('remplace l\'ancien jeton et ne l\'expose pas dans le profil', function () {
        $user = User::factory()->create(['jeton_push' => null]);
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/v1/me/jeton-push', ['jetonPush' => 'premier'])->assertOk();
        $this->putJson('/api/v1/me/jeton-push', ['jetonPush' => 'second'])->assertOk();

        expect($user->fresh()->jeton_push)->toBe('second');
        $this->getJson('/api/v1/me')->assertJsonMissingPath('jetonPush')->assertJsonMissingPath('jeton_push');
    });

    it('renvoie 422 quand le jeton est absent ou trop long', function () {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->putJson('/api/v1/me/jeton-push', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.jetonPush.0', 'Le champ jeton push est obligatoire.');
        $this->putJson('/api/v1/me/jeton-push', ['jetonPush' => str_repeat('a', 4097)])->assertUnprocessable();
    });

    it('renvoie 401 sans jeton', function () {
        $this->putJson('/api/v1/me/jeton-push', ['jetonPush' => 'x'])->assertUnauthorized();
    });
});

describe('limitation de débit', function () {
    it('limite PUT /me/mot-de-passe à 5 requêtes par minute et par utilisateur', function () {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $faux = ['mot_de_passe_actuel' => 'faux', 'nouveau_mot_de_passe' => 'nouveau-mot-de-passe', 'confirmation' => 'nouveau-mot-de-passe'];

        foreach (range(1, 5) as $i) {
            $this->putJson('/api/v1/me/mot-de-passe', $faux)->assertUnprocessable();
        }
        $this->putJson('/api/v1/me/mot-de-passe', $faux)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Trop de tentatives. Réessayez dans quelques instants.');

        // Même avec le bon mot de passe actuel, la limite s'applique.
        $this->putJson('/api/v1/me/mot-de-passe', array_merge($faux, ['mot_de_passe_actuel' => 'motdepasse']))->assertStatus(429);
        expect(Hash::check('motdepasse', $user->fresh()->password))->toBeTrue();
    });

    it('applique la limite par utilisateur, pas globalement', function () {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $faux = ['mot_de_passe_actuel' => 'faux', 'nouveau_mot_de_passe' => 'nouveau-mot-de-passe', 'confirmation' => 'nouveau-mot-de-passe'];

        foreach (range(1, 6) as $i) {
            $this->actingAs($a, 'sanctum')->putJson('/api/v1/me/mot-de-passe', $faux);
        }
        $this->actingAs($a, 'sanctum')->putJson('/api/v1/me/mot-de-passe', $faux)->assertStatus(429);
        $this->actingAs($b, 'sanctum')->putJson('/api/v1/me/mot-de-passe', $faux)->assertUnprocessable();
    });

    it('limite PUT /me à 30 requêtes par minute', function () {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $donnees = ['nom' => 'A', 'prenom' => 'B', 'telephone' => $user->telephone];

        foreach (range(1, 30) as $i) {
            $this->putJson('/api/v1/me', $donnees)->assertOk();
        }
        $this->putJson('/api/v1/me', $donnees)->assertStatus(429);
    });

    it('limite PUT /me/jeton-push à 30 requêtes par minute', function () {
        $this->actingAs(User::factory()->create(), 'sanctum');

        foreach (range(1, 30) as $i) {
            $this->putJson('/api/v1/me/jeton-push', ['jetonPush' => "jeton-$i"])->assertOk();
        }
        $this->putJson('/api/v1/me/jeton-push', ['jetonPush' => 'jeton-31'])->assertStatus(429);
    });

    it('ne limite pas GET /me', function () {
        $this->actingAs(User::factory()->create(), 'sanctum');

        foreach (range(1, 40) as $i) {
            $this->getJson('/api/v1/me')->assertOk();
        }
    });
});

describe('colonne jeton_push', function () {
    it('est nullable, de type texte et non unique', function () {
        $colonne = collect(Schema::getColumns('users'))->firstWhere('name', 'jeton_push');
        expect($colonne)->not->toBeNull()
            ->and($colonne['nullable'])->toBeTrue()
            ->and($colonne['type_name'])->toBe('text');

        $uniques = collect(Schema::getIndexes('users'))
            ->filter(fn ($index) => $index['unique'] && in_array('jeton_push', $index['columns']));
        expect($uniques)->toBeEmpty();
    });

    it('permet à deux utilisateurs d\'enregistrer le même jeton', function () {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a, 'sanctum')->putJson('/api/v1/me/jeton-push', ['jetonPush' => 'jeton-partage'])->assertOk();
        $this->actingAs($b, 'sanctum')->putJson('/api/v1/me/jeton-push', ['jetonPush' => 'jeton-partage'])->assertOk();

        expect($a->fresh()->jeton_push)->toBe('jeton-partage')
            ->and($b->fresh()->jeton_push)->toBe('jeton-partage');
    });

    it('est vide par défaut (nullable)', function () {
        expect(User::factory()->create()->fresh()->jeton_push)->toBeNull();
    });
});
