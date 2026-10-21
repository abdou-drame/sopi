<?php

use App\Models\Avis;
use App\Models\Prestataire;
use App\Models\Retrait;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('filtres par nom du prestataire — avis et retraits', function () {
    it('filtre les retraits par nom d\'entreprise ou par identifiant', function () {
        adminConnecte();
        $garage = Prestataire::factory()->create(['nom_entreprise' => 'Garage Soleil']);
        $salon = Prestataire::factory()->create(['nom_entreprise' => 'Salon Lune']);
        $r1 = Retrait::factory()->create(['prestataire_id' => $garage->id]);
        Retrait::factory()->create(['prestataire_id' => $salon->id]);

        foreach (['soleil', $garage->id] as $filtre) {
            expect(collect($this->getJson('/api/v1/admin/retraits?prestataire='.$filtre)->assertOk()->json('data'))->pluck('id')->all())->toBe([$r1->id]);
        }
    });

    it('filtre les avis par nom d\'entreprise ou par identifiant', function () {
        adminConnecte();
        $garage = Prestataire::factory()->create(['nom_entreprise' => 'Garage Soleil']);
        $salon = Prestataire::factory()->create(['nom_entreprise' => 'Salon Lune']);
        $a1 = Avis::factory()->create(['prestataire_id' => $garage->id]);
        Avis::factory()->create(['prestataire_id' => $salon->id]);

        foreach (['soleil', $garage->id] as $filtre) {
            expect(collect($this->getJson('/api/v1/admin/avis?prestataire='.$filtre)->assertOk()->json('data'))->pluck('id')->all())->toBe([$a1->id]);
        }
    });
});
