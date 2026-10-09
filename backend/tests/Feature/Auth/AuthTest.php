<?php

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function donneesInscription(array $surcharge = []): array
{
    return array_merge([
        'nom' => 'Diop',
        'prenom' => 'Awa',
        'telephone' => '77 123 45 67',
        'email' => 'awa@example.com',
        'password' => 'motdepasse1',
    ], $surcharge);
}

describe('inscription', function () {
    it('crée un compte client actif et renvoie un jeton', function () {
        $reponse = $this->postJson('/api/v1/auth/inscription', donneesInscription());

        $reponse->assertCreated()
            ->assertJsonPath('user.telephone', '+221771234567')
            ->assertJsonPath('user.role', 'client')
            ->assertJsonPath('user.statut', 'actif')
            ->assertJsonMissingPath('user.password')
            ->assertJsonStructure(['token', 'user' => ['id', 'nom', 'prenom', 'telephone', 'email', 'role', 'statut']]);

        $user = User::firstWhere('telephone', '+221771234567');
        expect($user)->not->toBeNull()
            ->and($user->password)->not->toBe('motdepasse1')
            ->and($user->id)->toMatch('/^[0-9a-f-]{36}$/');

        $this->withToken($reponse->json('token'))->getJson('/api/v1/auth/moi')->assertOk();
    });

    it('accepte une inscription sans e-mail', function () {
        $this->postJson('/api/v1/auth/inscription', donneesInscription(['email' => null]))->assertCreated();
        expect(User::first()->email)->toBeNull();
    });

    it('refuse un téléphone déjà utilisé, quel que soit son format', function () {
        User::factory()->create(['telephone' => '+221771234567']);

        $this->postJson('/api/v1/auth/inscription', donneesInscription(['email' => 'autre@example.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['telephone'])
            ->assertJsonPath('errors.telephone.0', 'Ce numéro de téléphone est déjà utilisé.');
    });

    it('refuse un e-mail déjà utilisé', function () {
        User::factory()->create(['email' => 'awa@example.com']);

        $this->postJson('/api/v1/auth/inscription', donneesInscription(['telephone' => '78 000 00 00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('impose le rôle client et le statut actif même si l\'appelant envoie autre chose', function () {
        $this->postJson('/api/v1/auth/inscription', donneesInscription([
            'role' => 'administrateur',
            'statut' => 'suspendu',
        ]))->assertCreated()->assertJsonPath('user.role', 'client')->assertJsonPath('user.statut', 'actif');

        $user = User::first();
        expect($user->role)->toBe(Role::Client)->and($user->statut)->toBe(StatutCompte::Actif);
    });

    it('valide les champs avec des messages en français', function () {
        $this->postJson('/api/v1/auth/inscription', [
            'telephone' => '12345',
            'password' => 'court',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['nom', 'prenom', 'telephone', 'password'])
            ->assertJsonPath('errors.nom.0', 'Le champ nom est obligatoire.')
            ->assertJsonPath('errors.password.0', 'Le champ mot de passe doit contenir au moins 8 caractères.');
    });
});

describe('connexion', function () {
    it('connecte un compte actif et renvoie le jeton et l\'utilisateur', function () {
        User::factory()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertOk()
            ->assertJsonPath('user.telephone', '+221771234567')
            ->assertJsonStructure(['token', 'user' => ['id', 'role', 'statut']])
            ->assertJsonMissingPath('user.password');
    });

    it('renvoie le même message pour un mauvais mot de passe et un téléphone inconnu', function () {
        User::factory()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);

        $mauvaisMdp = $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'faux-mot-de-passe']);
        $inconnu = $this->postJson('/api/v1/auth/connexion', ['telephone' => '781112233', 'password' => 'motdepasse1']);

        $mauvaisMdp->assertStatus(401);
        $inconnu->assertStatus(401);
        expect($mauvaisMdp->json())->toBe($inconnu->json())
            ->and($mauvaisMdp->json('message'))->toBe('Téléphone ou mot de passe incorrect.');
    });

    it('refuse un compte suspendu', function () {
        User::factory()->suspendu()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Votre compte est suspendu. Contactez le support.')
            ->assertJsonMissingPath('token');
    });

    it('ne révèle pas le statut d\'un compte suspendu avec un mauvais mot de passe', function () {
        User::factory()->suspendu()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'faux'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Téléphone ou mot de passe incorrect.');
    });

    it('refuse les comptes qui ne sont pas actifs', function (StatutCompte $statut) {
        User::factory()->statut($statut)->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertForbidden();
    })->with([StatutCompte::EnAttenteValidation, StatutCompte::Refuse, StatutCompte::Banni]);
});

describe('déconnexion et profil', function () {
    it('révoque le jeton courant à la déconnexion', function () {
        User::factory()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);
        $token = $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/deconnexion')->assertOk();

        expect(\Laravel\Sanctum\PersonalAccessToken::count())->toBe(0);
        // Le guard met l'utilisateur en cache dans la même requête de test : on le réinitialise.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/moi')->assertUnauthorized();
    });

    it('renvoie l\'utilisateur connecté sur /moi', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/moi')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonMissingPath('password');
    });

    it('renvoie 401 sur /moi sans jeton', function () {
        $this->getJson('/api/v1/auth/moi')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    });

    it('renvoie 401 en JSON même sans en-tête Accept', function () {
        $this->get('/api/v1/auth/moi')->assertUnauthorized()->assertJsonStructure(['message']);
    });
});

describe('limitation de débit', function () {
    it('bloque la connexion après 5 tentatives par minute avec un message en français', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'faux'])
                ->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'faux'])
            ->assertStatus(429);
    });

    it('bloque l\'inscription après 5 requêtes par minute', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/inscription', [])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/inscription', [])->assertStatus(429);
    });
});

it('ne renvoie jamais le mot de passe ni le jeton dans une erreur de validation', function () {
    $reponse = $this->postJson('/api/v1/auth/inscription', ['password' => 'secret-court', 'telephone' => 'x']);

    $reponse->assertStatus(422);
    expect($reponse->getContent())->not->toContain('secret-court');
});

it('renvoie les erreurs courantes de l\'API en français', function () {
    $this->getJson('/api/v1/auth/moi')->assertUnauthorized()->assertExactJson(['message' => 'Authentification requise.']);
    $this->getJson('/api/v1/inexistant')->assertNotFound()->assertJsonPath('message', 'Ressource introuvable.');
    $this->getJson('/api/v1/auth/connexion')->assertStatus(405)->assertJsonPath('message', 'Méthode non autorisée.');

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'x']);
    }
    $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'x'])
        ->assertStatus(429)
        ->assertJsonPath('message', 'Trop de tentatives. Réessayez dans quelques instants.')
        ->assertHeader('Retry-After');
});
