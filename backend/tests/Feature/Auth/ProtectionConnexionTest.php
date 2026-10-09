<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function tenter(string $telephone, string $motDePasse)
{
    return test()->postJson('/api/v1/auth/connexion', ['telephone' => $telephone, 'password' => $motDePasse]);
}

function echouer(string $telephone, int $fois): void
{
    for ($i = 0; $i < $fois; $i++) {
        tenter($telephone, 'mauvais-mot-de-passe')->assertStatus(401);
    }
}

beforeEach(function () {
    Cache::flush();
    $this->travelTo(now()->startOfDay()->addHours(10));
    User::factory()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);
});

describe('blocage après des échecs consécutifs', function () {
    it('bloque après 5 échecs avec un message en français indiquant le délai', function () {
        echouer('771234567', 5);

        tenter('771234567', 'mauvais-mot-de-passe')
            ->assertStatus(429)
            ->assertJsonPath('message', "Trop d'échecs de connexion. Réessayez dans 15 minutes.")
            ->assertHeader('Retry-After', '900');
    });

    it('ne bloque pas avant le 5e échec', function () {
        echouer('771234567', 4);

        tenter('771234567', 'motdepasse1')->assertOk();
    });

    it('refuse le bon mot de passe pendant le blocage', function () {
        echouer('771234567', 5);

        tenter('771234567', 'motdepasse1')->assertStatus(429)->assertJsonMissingPath('token');

        $this->travel(14)->minutes();
        tenter('771234567', 'motdepasse1')
            ->assertStatus(429)
            ->assertJsonPath('message', "Trop d'échecs de connexion. Réessayez dans moins d'une minute.");
    });

    it('indique le délai restant qui diminue avec le temps', function () {
        echouer('771234567', 5);

        $this->travel(10)->minutes();
        tenter('771234567', 'motdepasse1')
            ->assertStatus(429)
            ->assertJsonPath('message', "Trop d'échecs de connexion. Réessayez dans 5 minutes.");

        $this->travel(4)->minutes();
        $this->travel(30)->seconds();
        tenter('771234567', 'motdepasse1')
            ->assertStatus(429)
            ->assertJsonPath('message', "Trop d'échecs de connexion. Réessayez dans moins d'une minute.");
    });

    it('débloque après 15 minutes', function () {
        echouer('771234567', 5);

        $this->travel(15)->minutes();

        tenter('771234567', 'motdepasse1')->assertOk()->assertJsonStructure(['token']);
    });

    it('remet le compteur à zéro après une connexion réussie', function () {
        echouer('771234567', 4);
        tenter('771234567', 'motdepasse1')->assertOk();

        // Si le compteur n'avait pas été remis à zéro, le 1er échec ici serait le 5e.
        echouer('771234567', 4);
        tenter('771234567', 'motdepasse1')->assertOk();
    });

    it('ne compte pas des échecs séparés de plus de 15 minutes', function () {
        echouer('771234567', 4);
        $this->travel(16)->minutes();

        echouer('771234567', 1);
        tenter('771234567', 'motdepasse1')->assertOk();
    });

    it('applique le blocage par numéro, sans toucher aux autres', function () {
        User::factory()->create(['telephone' => '+221781112233', 'password' => 'motdepasse1']);
        echouer('771234567', 5);

        tenter('781112233', 'motdepasse1')->assertOk();
    });

    it('bloque un numéro inconnu exactement comme un numéro existant', function () {
        echouer('701112233', 5); // aucun compte avec ce numéro

        $inconnu = tenter('701112233', 'motdepasse1');
        $inconnu->assertStatus(429)
            ->assertJsonPath('message', "Trop d'échecs de connexion. Réessayez dans 15 minutes.");

        // Même réponse que pour un compte existant bloqué.
        echouer('771234567', 5);
        $existant = tenter('771234567', 'motdepasse1');
        expect($existant->status())->toBe($inconnu->status())
            ->and($existant->json())->toBe($inconnu->json());

        $this->travel(15)->minutes();
        tenter('701112233', 'motdepasse1')->assertStatus(401); // débloqué, identifiants faux
    });

    it('traite pareillement le numéro quelle que soit sa façon de l\'écrire', function () {
        echouer('77 123 45 67', 3);
        echouer('+221771234567', 2);

        tenter('771234567', 'motdepasse1')->assertStatus(429);
    });
});

describe('expiration des jetons', function () {
    it('expire les jetons après 30 jours par configuration', function () {
        expect(config('sanctum.expiration'))->toBe(60 * 24 * 30);
    });

    it('accepte un jeton avant 30 jours', function () {
        $token = tenter('771234567', 'motdepasse1')->json('token');

        $this->travel(29)->days();
        $this->withToken($token)->getJson('/api/v1/auth/moi')->assertOk();
    });

    it('refuse un jeton expiré avec une réponse 401', function () {
        $token = tenter('771234567', 'motdepasse1')->json('token');

        $this->travel(30)->days();
        $this->travel(1)->minutes();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/moi')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Authentification requise.');
    });
});
