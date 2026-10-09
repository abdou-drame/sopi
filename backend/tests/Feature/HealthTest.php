<?php

use Carbon\Carbon;

it('renvoie l\'état de santé de l\'application avec la base joignable', function () {
    $reponse = $this->getJson('/api/v1/health');

    $reponse->assertOk()
        ->assertJson([
            'status' => 'ok',
            'app' => 'Sopi',
            'version' => config('app.version'),
            'database' => 'ok',
        ])
        ->assertJsonStructure(['status', 'app', 'version', 'database', 'time']);

    // L'heure doit être au format ISO 8601 valide.
    expect(Carbon::parse($reponse->json('time')))->toBeInstanceOf(Carbon::class);
    expect($reponse->json('time'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

it('utilise le fuseau Africa/Dakar et la langue française', function () {
    expect(config('app.timezone'))->toBe('Africa/Dakar')
        ->and(config('app.locale'))->toBe('fr');
});

it('renvoie 503 et database « erreur » quand la base est indisponible', function () {
    config(['database.connections.sqlite.database' => '/dossier/inexistant/sopi.sqlite']);
    DB::purge();

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertJson(['status' => 'ok', 'app' => 'Sopi', 'database' => 'erreur'])
        ->assertJsonStructure(['status', 'app', 'version', 'database', 'time']);
});

it('renvoie la version lue depuis la variable d\'environnement APP_VERSION', function () {
    // phpunit.xml définit APP_VERSION=1.2.3-test.
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('version', '1.2.3-test');
});

it('utilise « 0.0.0-dev » comme version par défaut quand APP_VERSION est absente', function () {
    $script = 'require "vendor/autoload.php"; echo (require "config/app.php")["version"];';
    $env = ['APP_VERSION' => false]; // false = retire la variable de l'environnement hérité

    $process = new Symfony\Component\Process\Process([PHP_BINARY, '-r', $script], base_path(), $env);
    $process->mustRun();

    expect($process->getOutput())->toBe('0.0.0-dev');
});
