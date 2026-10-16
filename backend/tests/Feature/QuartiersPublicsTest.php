<?php

use App\Enums\StatutCompte;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function prestataireAuQuartier(string $quartier, StatutCompte $statut = StatutCompte::Actif): Prestataire
{
    return Prestataire::factory()
        ->for(User::factory()->prestataire()->statut($statut), 'user')
        ->create(['quartier' => $quartier]);
}

describe('GET /quartiers', function () {
    it('est public et renvoie une liste vide sans prestataire', function () {
        $this->getJson('/api/v1/quartiers')->assertOk()->assertExactJson(['data' => []]);
    });

    it('liste les quartiers distincts triés, sans doublon de casse ni d\'espaces', function () {
        prestataireAuQuartier('Malika');
        prestataireAuQuartier('malika ');
        prestataireAuQuartier('Keur Massar Nord');
        prestataireAuQuartier('keur massar nord');
        prestataireAuQuartier('Jaxaay');
        prestataireAuQuartier('Édouard');

        $quartiers = $this->getJson('/api/v1/quartiers')->assertOk()->json('data');

        expect($quartiers)->toHaveCount(4)
            ->and(array_map('mb_strtolower', $quartiers))->toBe(['édouard', 'jaxaay', 'keur massar nord', 'malika'])
            ->and($quartiers)->toBe(array_map('trim', $quartiers));
    });

    it('ignore les prestataires dont le compte n\'est pas actif', function () {
        prestataireAuQuartier('Malika');
        prestataireAuQuartier('Jaxaay', StatutCompte::Suspendu);
        prestataireAuQuartier('Yeumbeul Nord', StatutCompte::EnAttenteValidation);
        prestataireAuQuartier('Yeumbeul Sud', StatutCompte::Banni);

        $this->getJson('/api/v1/quartiers')->assertOk()->assertExactJson(['data' => ['Malika']]);
    });
});
