<?php

it('autorise les origines configurées (en-tête CORS présent)', function () {
    $this->getJson('/api/v1/health', ['Origin' => 'https://sopi-admin.duckdns.org'])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://sopi-admin.duckdns.org');
});

it('répond à la requête préliminaire (OPTIONS) d\'une origine autorisée', function () {
    $this->call('OPTIONS', '/api/v1/health', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost:5173',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ])->assertSuccessful()
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
});

it('n\'ajoute pas d\'en-tête CORS pour une origine non autorisée', function () {
    $this->getJson('/api/v1/health', ['Origin' => 'https://site-malveillant.example'])
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('lit la liste des origines depuis CORS_ALLOWED_ORIGINS, séparée par des virgules', function () {
    expect(config('cors.allowed_origins'))
        ->toBe(['https://sopi-admin.duckdns.org', 'http://localhost:5173']);
});
