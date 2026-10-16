<?php

use App\Models\Metier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GET /metiers', function () {
    it('est public et liste les métiers actifs triés par nom', function () {
        $this->getJson('/api/v1/metiers')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.code', 'coiffure')
            ->assertJsonPath('data.1.nom', 'Mécanique')
            ->assertJsonPath('data.2.code', 'pressing')
            ->assertJsonStructure(['data' => [['id', 'code', 'nom']]]);
    });

    it('n\'affiche pas un métier inactif', function () {
        Metier::query()->where('code', 'pressing')->update(['actif' => false]);

        $reponse = $this->getJson('/api/v1/metiers')->assertOk();

        expect(collect($reponse->json('data'))->pluck('code')->all())->toBe(['coiffure', 'mecanique']);
    });

    it('ne renvoie ni champ d\'administration ni compteur', function () {
        $this->getJson('/api/v1/metiers')->assertOk()->assertJsonMissingPath('data.0.actif')->assertJsonMissingPath('data.0.nombrePrestataires');
    });
});
