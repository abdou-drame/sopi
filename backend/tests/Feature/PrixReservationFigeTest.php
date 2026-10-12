<?php

use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Paiement\PaiementService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['sopi.paiement.driver' => 'fake']);
    app()->forgetInstance(PaiementService::class);
});

/** Prestataire avec un créneau à venir libre et une prestation au prix donné. */
function offrePrixFige(string $prix = '15000.00'): array
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $debut = now()->addDays(2)->startOfHour();
    $creneau = Creneau::factory()->for($prestataire)->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);

    return [$prestataire, $creneau, Prestation::factory()->for($prestataire)->create(['prix' => $prix])];
}

/** Le client verrouille le créneau puis réserve la prestation par l'API, avec des champs supplémentaires éventuels. */
function reserverViaApi(User $client, Creneau $creneau, Prestation $prestation, array $extra = []): TestResponse
{
    test()->actingAs($client, 'sanctum');
    test()->postJson('/api/v1/creneaux/'.$creneau->id.'/verrou')->assertSuccessful();

    return test()->postJson('/api/v1/reservations', ['creneau_id' => $creneau->id, 'prestation_id' => $prestation->id] + $extra);
}

describe('figeage à la création', function () {
    it('fige le prix de la prestation dans la réservation', function () {
        [, $creneau, $prestation] = offrePrixFige();

        $reponse = reserverViaApi(User::factory()->create(), $creneau, $prestation)->assertCreated()
            ->assertJsonPath('prestation.prix', 15000);

        expect(Reservation::findOrFail($reponse->json('id'))->prix_applique)->toBe('15000.00');
    });

    it('ne laisse aucun champ du client influencer le prix', function () {
        [, $creneau, $prestation] = offrePrixFige();

        $reponse = reserverViaApi(User::factory()->create(), $creneau, $prestation, [
            'prix_applique' => '1.00', 'prix' => '1.00', 'prestation' => ['prix' => 1], 'montant' => '1.00',
        ])->assertCreated()->assertJsonPath('prestation.prix', 15000);

        expect(Reservation::findOrFail($reponse->json('id'))->prix_applique)->toBe('15000.00');
    });

    it('n\'a pas de fillable : le prix ne s\'affecte pas en masse', function () {
        expect((new Reservation)->getFillable())->toBe([]);
    });

    it('garde l\'ancien prix quand la prestation change ensuite : réservation, réponses et paiement', function () {
        [$prestataire, $creneau, $prestation] = offrePrixFige();
        $client = User::factory()->create();
        $id = reserverViaApi($client, $creneau, $prestation)->assertCreated()->json('id');

        $prestation->update(['prix' => '20000.00']);
        expect(Reservation::findOrFail($id)->prix_applique)->toBe('15000.00');

        // Côté client : détail et liste.
        $this->actingAs($client, 'sanctum');
        $this->getJson('/api/v1/reservations/'.$id)->assertOk()->assertJsonPath('prestation.prix', 15000);
        $this->getJson('/api/v1/reservations')->assertOk()->assertJsonPath('data.0.prestation.prix', 15000);

        // Côté prestataire : liste, détail, puis acceptation.
        $this->actingAs($prestataire->user, 'sanctum');
        $this->getJson('/api/v1/prestataire/reservations')->assertOk()->assertJsonPath('data.0.prestation.prix', 15000);
        $this->getJson('/api/v1/prestataire/reservations/'.$id)->assertOk()->assertJsonPath('prestation.prix', 15000);
        $this->postJson('/api/v1/prestataire/reservations/'.$id.'/accepter')->assertOk()->assertJsonPath('prestation.prix', 15000);

        // Paiement : 15 000 figé, prestation à 20 000 → acompte 4 500, reste 10 500.
        $this->actingAs($client, 'sanctum');
        $this->postJson('/api/v1/reservations/'.$id.'/payer')->assertCreated()
            ->assertJsonPath('paiement.montantAcompte', '4500.00')
            ->assertJsonPath('montantRestantSurPlace', '10500.00');
        expect(Paiement::sole()->commission)->toBe('1500.00');
    });

    it('ne recalcule jamais un paiement déjà créé quand le prix de la prestation change', function () {
        [, $creneau, $prestation] = offrePrixFige();
        $client = User::factory()->create();
        $id = reserverViaApi($client, $creneau, $prestation)->assertCreated()->json('id');
        Reservation::whereKey($id)->update(['statut' => 'acceptee', 'acceptee_le' => now()]);

        $premier = $this->postJson('/api/v1/reservations/'.$id.'/payer')->assertCreated()->json();
        $prestation->update(['prix' => '30000.00']);
        $second = $this->postJson('/api/v1/reservations/'.$id.'/payer')->assertOk()->json();

        expect($second)->toBe($premier)
            ->and(Paiement::sole()->montant_acompte)->toBe('4500.00');
    });
});

describe('garde-fous de la base et migration', function () {
    it('refuse un prix appliqué absent, nul ou négatif, à l\'insertion comme à la mise à jour', function (?string $prix) {
        $reservation = Reservation::factory()->create();

        $nouvelle = (array) DB::table('reservations')->where('id', $reservation->id)->first();
        $nouvelle['id'] = (string) Str::uuid();
        $nouvelle['code_reservation'] = null;
        $nouvelle['statut'] = 'annulee'; // hors de l'index « une réservation active par créneau »
        $nouvelle['prix_applique'] = $prix;

        expect(fn () => DB::table('reservations')->insert($nouvelle))->toThrow(QueryException::class)
            ->and(fn () => DB::table('reservations')->where('id', $reservation->id)->update(['prix_applique' => $prix]))->toThrow(QueryException::class);
    })->with([[null], ['0.00'], ['-5.00']]);

    it('remplit les réservations existantes avec le prix actuel de leur prestation (SQL de la migration)', function () {
        $a = Reservation::factory()->create();
        $b = Reservation::factory()->create();
        $a->prestation->update(['prix' => '12345.00']);
        $b->prestation->update(['prix' => '800.00']);

        // État d'avant la migration : colonne vide (on retire le déclencheur de mise à jour le temps du test).
        DB::statement('DROP TRIGGER reservations_prix_applique_mise_a_jour');
        DB::table('reservations')->update(['prix_applique' => null]);

        DB::statement('UPDATE reservations SET prix_applique = (SELECT prestations.prix FROM prestations WHERE prestations.id = reservations.prestation_id)');

        expect(Reservation::findOrFail($a->id)->prix_applique)->toBe('12345.00')
            ->and(Reservation::findOrFail($b->id)->prix_applique)->toBe('800.00');
    });

    it('rejoue la migration sur une base remplie : colonne remplie, puis obligatoire', function () {
        $migration = require base_path('database/migrations/2026_10_20_000001_ajoute_prix_applique_aux_reservations.php');
        $reservation = Reservation::factory()->create();
        $reservation->prestation->update(['prix' => '9000.00']);

        $migration->down();
        expect(Schema::hasColumn('reservations', 'prix_applique'))->toBeFalse();

        $migration->up();
        expect(Schema::hasColumn('reservations', 'prix_applique'))->toBeTrue()
            ->and(Reservation::findOrFail($reservation->id)->prix_applique)->toBe('9000.00')
            ->and(fn () => DB::table('reservations')->where('id', $reservation->id)->update(['prix_applique' => null]))->toThrow(QueryException::class);
    });

    it('donne à la fabrique le prix de la prestation de la réservation', function () {
        $reservation = Reservation::factory()->create();

        expect($reservation->prix_applique)->toBe($reservation->prestation->prix);
    });
});
