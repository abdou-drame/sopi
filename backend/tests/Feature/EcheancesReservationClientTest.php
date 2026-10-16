<?php

use App\Http\Resources\ReservationResource;
use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2030-01-15 08:00:00', 'UTC'));
    config(['sopi.reservations.delai_reponse_heures' => 12, 'sopi.reservations.delai_paiement_heures' => 2]);
});

afterEach(fn () => Carbon::setTestNow());

/** Réservation du client, créée à 08:00 UTC (heure figée par le test), dans l'état de la fabrique donné. */
function reservationAvecEcheances(User $client, string $etat, array $attributs = []): Reservation
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $prestation = Prestation::factory()->for($prestataire)->create();
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => now()->addDays(3), 'date_fin' => now()->addDays(3)->addHour()]);

    return Reservation::factory()->$etat()->create(array_merge([
        'client_id' => $client->id,
        'creneau_id' => $creneau->id,
        'prestation_id' => $prestation->id,
    ], $attributs));
}

describe('paiementAvant et reponseAvant (côté client)', function () {
    it('en_attente : reponseAvant = création + 12 h, paiementAvant null', function () {
        $client = User::factory()->create();
        $r = reservationAvecEcheances($client, 'enAttente');

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/reservations/'.$r->id)
            ->assertOk()
            ->assertJsonPath('reponseAvant', '2030-01-15T20:00:00+00:00')
            ->assertJsonPath('paiementAvant', null);
    });

    it('acceptee non payée : paiementAvant = acceptation + 2 h, reponseAvant null', function () {
        $client = User::factory()->create();
        $r = reservationAvecEcheances($client, 'acceptee', ['acceptee_le' => Carbon::parse('2030-01-15 09:30:00', 'UTC')]);

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/reservations/'.$r->id)
            ->assertOk()
            ->assertJsonPath('paiementAvant', '2030-01-15T11:30:00+00:00')
            ->assertJsonPath('reponseAvant', null);
    });

    it('suit la configuration du délai de paiement', function () {
        config(['sopi.reservations.delai_paiement_heures' => 5]);
        $client = User::factory()->create();
        $r = reservationAvecEcheances($client, 'acceptee', ['acceptee_le' => Carbon::parse('2030-01-15 09:30:00', 'UTC')]);

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/reservations/'.$r->id)
            ->assertJsonPath('paiementAvant', '2030-01-15T14:30:00+00:00');
    });

    it('acceptee avec un paiement réussi : les deux sont null', function () {
        $client = User::factory()->create();
        $r = reservationAvecEcheances($client, 'acceptee');
        Paiement::factory()->reussi()->create(['reservation_id' => $r->id]);

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/reservations/'.$r->id)
            ->assertJsonPath('paiementAvant', null)
            ->assertJsonPath('reponseAvant', null);
    });

    it('null pour confirmee, en_cours, terminee, refusee et annulee', function (string $etat) {
        $client = User::factory()->create();
        $r = reservationAvecEcheances($client, $etat);

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/reservations/'.$r->id)
            ->assertOk()
            ->assertJsonPath('paiementAvant', null)
            ->assertJsonPath('reponseAvant', null);
    })->with(['confirmee', 'enCours', 'terminee', 'refusee', 'annulee']);

    it('les expose aussi dans la liste GET /reservations', function () {
        $client = User::factory()->create();
        reservationAvecEcheances($client, 'enAttente');
        reservationAvecEcheances($client, 'acceptee', ['acceptee_le' => Carbon::parse('2030-01-15 09:30:00', 'UTC')]);

        $liste = collect($this->actingAs($client, 'sanctum')->getJson('/api/v1/reservations')->assertOk()->json('data'))->keyBy('statut');

        expect($liste['en_attente']['reponseAvant'])->toBe('2030-01-15T20:00:00+00:00')
            ->and($liste['en_attente']['paiementAvant'])->toBeNull()
            ->and($liste['acceptee']['paiementAvant'])->toBe('2030-01-15T11:30:00+00:00')
            ->and($liste['acceptee']['reponseAvant'])->toBeNull();
    });

    it('paiementAvant reste donné passé le délai tant que la réservation est acceptée, avec peutPayer à faux', function () {
        $client = User::factory()->create();
        $r = reservationAvecEcheances($client, 'acceptee', ['acceptee_le' => Carbon::parse('2030-01-15 05:00:00', 'UTC')]);

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/reservations/'.$r->id)
            ->assertJsonPath('paiementAvant', '2030-01-15T07:00:00+00:00')
            ->assertJsonPath('peutPayer', false);
    });

    it('n\'ajoute rien à la réponse de création (ressource distincte)', function () {
        $client = User::factory()->create();
        $r = reservationAvecEcheances($client, 'enAttente');
        $json = (new ReservationResource($r->load('prestation.prestataire', 'creneau')))->toArray(request());

        expect($json)->not->toHaveKeys(['paiementAvant', 'reponseAvant']);
    });
});
