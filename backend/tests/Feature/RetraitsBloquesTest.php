<?php

use App\Enums\StatutRetrait;
use App\Models\Prestataire;
use App\Models\Retrait;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

describe('commande sopi:retraits:signaler-bloques', function () {
    it('signale un avertissement par retrait en cours depuis plus de 15 minutes, avec son seul identifiant', function () {
        $appels = [];
        Log::shouldReceive('warning')->andReturnUsing(function ($message, $contexte = []) use (&$appels) {
            $appels[] = [$message, $contexte];
        });
        $vieux = Retrait::factory()->create(['created_at' => now()->subMinutes(16), 'telephone_versement' => '+221771234567', 'montant' => '7777.00']);
        $tresVieux = Retrait::factory()->create(['created_at' => now()->subDay()]);

        $this->artisan('sopi:retraits:signaler-bloques')->expectsOutput('2 retrait(s) bloqué(s) signalé(s).')->assertSuccessful();

        expect($appels)->toHaveCount(2)
            ->and(array_column($appels, 1))->toBe([['retrait_id' => $tresVieux->id], ['retrait_id' => $vieux->id]])
            ->and(json_encode($appels))->not->toContain('771234567')->not->toContain('7777');
    });

    it('ignore les retraits récents, réussis et échoués', function () {
        Log::spy();
        Retrait::factory()->create(['created_at' => now()->subMinutes(5)]);
        Retrait::factory()->reussi()->create(['created_at' => now()->subDay()]);
        Retrait::factory()->echoue()->create(['created_at' => now()->subDay()]);

        $this->artisan('sopi:retraits:signaler-bloques')->expectsOutput('0 retrait(s) bloqué(s) signalé(s).')->assertSuccessful();

        Log::shouldNotHaveReceived('warning');
    });

    it('applique le seuil exact : 15 minutes pile est signalé, 14 minutes 59 non', function () {
        Log::spy();
        $this->travelTo(now()->startOfSecond());
        $pile = Retrait::factory()->create(['created_at' => now()->subMinutes(15)]);
        Retrait::factory()->create(['created_at' => now()->subMinutes(15)->addSecond()]);

        $this->artisan('sopi:retraits:signaler-bloques')->assertSuccessful();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $contexte) => $contexte['retrait_id'] === $pile->id);
    });

    it('suit le délai de la configuration', function () {
        Log::spy();
        config(['sopi.retraits.delai_blocage_minutes' => 60]);
        Retrait::factory()->create(['created_at' => now()->subMinutes(30)]);
        $bloque = Retrait::factory()->create(['created_at' => now()->subMinutes(61)]);

        $this->artisan('sopi:retraits:signaler-bloques')->assertSuccessful();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $contexte) => $contexte['retrait_id'] === $bloque->id);
    });

    it('ne modifie rien en base : ni statut, ni solde, ni dates, ni garde de recrédit', function () {
        $prestataire = Prestataire::factory()->avoirSolde(12000)->create();
        $retrait = Retrait::factory()->create(['prestataire_id' => $prestataire->id, 'created_at' => now()->subHours(3)]);
        $avant = [
            'retraits' => Retrait::query()->get()->map->getAttributes()->all(),
            'prestataires' => Prestataire::query()->get()->map->getAttributes()->all(),
        ];

        $this->artisan('sopi:retraits:signaler-bloques')->assertSuccessful();
        $this->artisan('sopi:retraits:signaler-bloques')->assertSuccessful();

        expect(Retrait::query()->get()->map->getAttributes()->all())->toBe($avant['retraits'])
            ->and(Prestataire::query()->get()->map->getAttributes()->all())->toBe($avant['prestataires'])
            ->and($retrait->fresh()->statut)->toBe(StatutRetrait::EnCours)
            ->and($retrait->fresh()->recredite_le)->toBeNull();
    });

    it('réussit sans rien signaler quand il n\'y a aucun retrait', function () {
        Log::spy();

        $this->artisan('sopi:retraits:signaler-bloques')->expectsOutput('0 retrait(s) bloqué(s) signalé(s).')->assertSuccessful();

        Log::shouldNotHaveReceived('warning');
    });

    it('est planifiée toutes les 10 minutes sans chevauchement', function () {
        $evenement = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'sopi:retraits:signaler-bloques'));

        expect($evenement)->not->toBeNull()->and($evenement->expression)->toBe('*/10 * * * *')->and($evenement->withoutOverlapping)->toBeTrue();
    });

    it('a un délai par défaut de 15 minutes', function () {
        expect(config('sopi.retraits.delai_blocage_minutes'))->toBe(15);
    });
});
