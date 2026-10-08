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
