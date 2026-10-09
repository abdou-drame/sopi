<?php

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

// Valeurs fictives, uniquement pour les tests.
function configurerAdmin(array $surcharge = []): void
{
    $valeurs = array_merge([
        'nom' => 'Ndiaye',
        'prenom' => 'Moussa',
        'telephone' => '77 000 11 22',
        'email' => 'admin@test.example',
        'password' => 'mot-de-passe-test-1',
    ], $surcharge);

    foreach ($valeurs as $champ => $valeur) {
        config(["sopi.admin.$champ" => $valeur]);
    }
}

it('crée un administrateur actif à partir de la configuration', function () {
    configurerAdmin();

    $this->artisan('sopi:creer-admin')
        ->expectsOutput('Administrateur créé.')
        ->assertSuccessful();

    $admin = User::firstWhere('telephone', '+221770001122');
    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe(Role::Administrateur)
        ->and($admin->statut)->toBe(StatutCompte::Actif)
        ->and($admin->email)->toBe('admin@test.example')
        ->and($admin->password)->not->toBe('mot-de-passe-test-1');
});

it('accepte un administrateur sans e-mail', function () {
    configurerAdmin(['email' => null]);

    $this->artisan('sopi:creer-admin')->assertSuccessful();

    expect(User::first()->email)->toBeNull();
});

it('est idempotente : ne crée ni ne modifie rien si l\'administrateur existe déjà', function () {
    configurerAdmin();
    $this->artisan('sopi:creer-admin')->assertSuccessful();
    $avant = User::first()->fresh();

    configurerAdmin(['nom' => 'Autre', 'password' => 'un-autre-mot-de-passe']);
    $this->artisan('sopi:creer-admin')
        ->expectsOutput("Un administrateur existe déjà avec ce téléphone. Rien n'a été créé ni modifié.")
        ->assertSuccessful();

    expect(User::count())->toBe(1);
    $apres = User::first()->fresh();
    expect($apres->nom)->toBe('Ndiaye')
        ->and($apres->password)->toBe($avant->password)
        ->and($apres->updated_at->equalTo($avant->updated_at))->toBeTrue();
});

it('refuse un téléphone déjà pris par un compte non administrateur, sans le modifier', function () {
    User::factory()->create(['telephone' => '+221770001122']);
    configurerAdmin();

    $this->artisan('sopi:creer-admin')->assertFailed();

    expect(User::count())->toBe(1)
        ->and(User::first()->role)->toBe(Role::Client);
});

it('s\'arrête avec une erreur quand une variable obligatoire manque', function (string $champ, string $variable) {
    configurerAdmin([$champ => null]);

    $this->artisan('sopi:creer-admin')
        ->expectsOutputToContain($variable)
        ->assertFailed();

    expect(User::count())->toBe(0);
})->with([
    ['nom', 'ADMIN_NOM'],
    ['prenom', 'ADMIN_PRENOM'],
    ['telephone', 'ADMIN_TELEPHONE'],
    ['password', 'ADMIN_PASSWORD'],
]);

it('s\'arrête avec une erreur quand le téléphone est invalide', function () {
    configurerAdmin(['telephone' => '12345']);

    $this->artisan('sopi:creer-admin')
        ->expectsOutputToContain('ADMIN_TELEPHONE')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('s\'arrête avec une erreur quand le mot de passe est trop court', function () {
    configurerAdmin(['password' => 'court']);

    $this->artisan('sopi:creer-admin')
        ->expectsOutputToContain('ADMIN_PASSWORD')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('n\'affiche jamais le mot de passe, le téléphone ni le nom', function () {
    $sortie = '';
    $lancer = function () use (&$sortie) {
        Artisan::call('sopi:creer-admin');
        $sortie .= Artisan::output();
    };

    configurerAdmin(['password' => 'court']);
    $lancer();
    configurerAdmin(['telephone' => '12345']);
    $lancer();
    configurerAdmin();
    $lancer(); // création
    $lancer(); // idempotence

    expect($sortie)->not->toBe('')
        ->and($sortie)->not->toContain('mot-de-passe-test-1')
        ->and($sortie)->not->toContain('court')
        ->and($sortie)->not->toContain('770001122')
        ->and($sortie)->not->toContain('12345')
        ->and($sortie)->not->toContain('Ndiaye');
});

it('permet à l\'administrateur créé de se connecter via /auth/connexion avec le rôle administrateur', function () {
    configurerAdmin();
    $this->artisan('sopi:creer-admin')->assertSuccessful();

    $this->postJson('/api/v1/auth/connexion', ['telephone' => '770001122', 'password' => 'mot-de-passe-test-1'])
        ->assertOk()
        ->assertJsonPath('user.role', 'administrateur')
        ->assertJsonPath('user.statut', 'actif')
        ->assertJsonStructure(['token']);
});

it('n\'est lancée ni par le seeder par défaut ni par un déploiement automatique', function () {
    $racine = base_path();
    $seeder = file_get_contents($racine.'/database/seeders/DatabaseSeeder.php');
    $nixpacks = file_get_contents($racine.'/nixpacks.toml');

    expect($seeder)->not->toContain('creer-admin')->and($nixpacks)->not->toContain('creer-admin');
});
