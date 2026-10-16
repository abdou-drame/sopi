<?php

use App\Enums\StatutCompte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

const MESSAGE_IDENTIFIANT_KO = 'Identifiant ou mot de passe incorrect.';

beforeEach(fn () => Cache::flush());

function seConnecter(array $corps)
{
    return test()->postJson('/api/v1/auth/connexion', $corps);
}

describe('connexion par identifiant', function () {
    it('connecte par adresse e-mail, quelle que soit la casse et les espaces', function () {
        $user = User::factory()->create(['email' => 'awa@exemple.test', 'password' => 'motdepasse1']);

        foreach (['awa@exemple.test', '  AWA@Exemple.TEST '] as $saisie) {
            seConnecter(['identifiant' => $saisie, 'password' => 'motdepasse1'])
                ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonStructure(['token']);
        }
    });

    it('connecte par numéro de téléphone, écrit de plusieurs façons', function () {
        $user = User::factory()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);

        foreach (['+221771234567', '77 123 45 67', '00221771234567'] as $saisie) {
            seConnecter(['identifiant' => $saisie, 'password' => 'motdepasse1'])->assertOk()->assertJsonPath('user.id', $user->id);
        }
    });

    it('accepte toujours l\'ancien champ telephone', function () {
        $user = User::factory()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);

        seConnecter(['telephone' => '77 123 45 67', 'password' => 'motdepasse1'])->assertOk()->assertJsonPath('user.id', $user->id);
    });

    it('donne la même réponse 401 pour un mauvais mot de passe, un e-mail inconnu et un téléphone inconnu', function () {
        User::factory()->create(['email' => 'awa@exemple.test', 'password' => 'motdepasse1']);

        $reponses = [
            seConnecter(['identifiant' => 'awa@exemple.test', 'password' => 'faux']),
            seConnecter(['identifiant' => 'personne@exemple.test', 'password' => 'faux']),
            seConnecter(['identifiant' => '771111111', 'password' => 'faux']),
        ];
        foreach ($reponses as $reponse) {
            $reponse->assertStatus(401)->assertExactJson(['message' => MESSAGE_IDENTIFIANT_KO]);
        }
    });

    it('exige un identifiant et un mot de passe', function () {
        seConnecter(['password' => 'x'])->assertStatus(422)->assertJsonPath('errors.identifiant.0', 'Saisissez votre adresse e-mail ou votre numéro de téléphone.');
        seConnecter(['identifiant' => 'awa@exemple.test'])->assertStatus(422)->assertJsonValidationErrors('password');
    });

    it('refuse les comptes non actifs seulement avec le bon mot de passe, comme pour le téléphone', function (StatutCompte $statut) {
        User::factory()->statut($statut)->create(['email' => 'awa@exemple.test', 'password' => 'motdepasse1']);

        seConnecter(['identifiant' => 'awa@exemple.test', 'password' => 'faux'])->assertStatus(401)->assertJsonPath('message', MESSAGE_IDENTIFIANT_KO);
        seConnecter(['identifiant' => 'awa@exemple.test', 'password' => 'motdepasse1'])->assertStatus(403)->assertJsonMissingPath('token');
    })->with([StatutCompte::Banni, StatutCompte::Suspendu, StatutCompte::EnAttenteValidation, StatutCompte::Refuse]);

    it('bloque après 5 échecs par identifiant normalisé et propose « Mot de passe oublié »', function () {
        User::factory()->create(['email' => 'awa@exemple.test', 'password' => 'motdepasse1']);

        foreach (['awa@exemple.test', 'AWA@exemple.test', ' awa@exemple.test', 'Awa@Exemple.test', 'awa@exemple.test'] as $saisie) {
            seConnecter(['identifiant' => $saisie, 'password' => 'faux'])->assertStatus(401);
        }

        $reponse = seConnecter(['identifiant' => 'awa@exemple.test', 'password' => 'motdepasse1'])->assertStatus(429);
        expect($reponse->json('message'))->toContain('Trop d\'échecs de connexion')->toContain('Mot de passe oublié')
            ->and($reponse->headers->get('Retry-After'))->not->toBeNull();
    });

    it('bloque un e-mail inconnu exactement comme un e-mail existant', function () {
        for ($i = 0; $i < 5; $i++) {
            seConnecter(['identifiant' => 'personne@exemple.test', 'password' => 'faux'])->assertStatus(401);
        }

        seConnecter(['identifiant' => 'personne@exemple.test', 'password' => 'faux'])->assertStatus(429)->assertJsonPath('message', fn ($m) => str_contains($m, 'Mot de passe oublié'));
    });

    it('ne mélange pas le blocage d\'un e-mail avec celui du téléphone du même compte', function () {
        User::factory()->create(['email' => 'awa@exemple.test', 'telephone' => '+221771234567', 'password' => 'motdepasse1']);

        for ($i = 0; $i < 5; $i++) {
            seConnecter(['identifiant' => 'awa@exemple.test', 'password' => 'faux']);
        }

        seConnecter(['identifiant' => 'awa@exemple.test', 'password' => 'motdepasse1'])->assertStatus(429);
        seConnecter(['identifiant' => '771234567', 'password' => 'motdepasse1'])->assertOk();
    });

    it('conserve le limiteur de 20 requêtes par minute et par adresse IP', function () {
        for ($i = 0; $i < 20; $i++) {
            seConnecter(['identifiant' => "inconnu{$i}@exemple.test", 'password' => 'faux'])->assertStatus(401);
        }

        seConnecter(['identifiant' => 'inconnu99@exemple.test', 'password' => 'faux'])->assertStatus(429);
    });
});
