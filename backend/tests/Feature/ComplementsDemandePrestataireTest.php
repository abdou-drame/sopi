<?php

use App\Enums\StatutReservation;
use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CreditSoldeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const URL_RESERVATIONS_PRESTATAIRE = '/api/v1/prestataire/reservations';

/** Réservation à 5 000 FCFA sur un créneau du prestataire, au statut donné. */
function reservationA5000(Prestataire $prestataire, StatutReservation $statut): Reservation
{
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create();
    $prestation = Prestation::factory()->for($prestataire)->create(['prix' => 5000]);

    return Reservation::factory()->create([
        'client_id' => User::factory()->create()->id,
        'creneau_id' => $creneau->id,
        'prestation_id' => $prestation->id,
        'prix_applique' => '5000.00',
        'statut' => $statut,
    ]);
}

function prestataireDesComplements(): Prestataire
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    test()->actingAs($prestataire->user, 'sanctum');

    return $prestataire;
}

describe('champs ajoutés', function () {
    it('donne acompte 1 500, commission 500 et montant crédité 1 000 pour 5 000 FCFA, quel que soit le statut', function (StatutReservation $statut) {
        $moi = prestataireDesComplements();
        $reservation = reservationA5000($moi, $statut);

        $detail = $this->getJson(URL_RESERVATIONS_PRESTATAIRE.'/'.$reservation->id)->assertOk()
            ->assertJsonPath('prestation.prix', 5000)
            ->assertJsonPath('acompte', 1500)
            ->assertJsonPath('commission', 500)
            ->assertJsonPath('montantCredite', 1000);

        $liste = $this->getJson(URL_RESERVATIONS_PRESTATAIRE)->assertOk()
            ->assertJsonPath('data.0.acompte', 1500)
            ->assertJsonPath('data.0.commission', 500)
            ->assertJsonPath('data.0.montantCredite', 1000);
        expect($liste->json('data.0.reponseAvant'))->toBe($detail->json('reponseAvant'));
    })->with(array_map(fn (StatutReservation $s) => [$s], StatutReservation::cases()));

    it('donne reponseAvant seulement pour une demande en attente, égale à création + délai configuré', function () {
        $moi = prestataireDesComplements();
        $enAttente = reservationA5000($moi, StatutReservation::EnAttente);
        $attendu = $enAttente->created_at->copy()->utc()->addHours((int) config('sopi.reservations.delai_reponse_heures'))->toIso8601String();

        $this->getJson(URL_RESERVATIONS_PRESTATAIRE.'/'.$enAttente->id)->assertOk()
            ->assertJsonPath('reponseAvant', $attendu);

        foreach (StatutReservation::cases() as $statut) {
            if ($statut === StatutReservation::EnAttente) {
                continue;
            }
            $autre = reservationA5000($moi, $statut);
            $this->getJson(URL_RESERVATIONS_PRESTATAIRE.'/'.$autre->id)->assertOk()
                ->assertJsonPath('reponseAvant', null);
        }
    });

    it('ne rend les montants que dans la liste et le détail, pas dans les réponses d\'action', function () {
        $moi = prestataireDesComplements();
        $reservation = reservationA5000($moi, StatutReservation::EnAttente);

        $this->postJson(URL_RESERVATIONS_PRESTATAIRE.'/'.$reservation->id.'/accepter')->assertOk()
            ->assertJsonPath('statut', 'acceptee')
            ->assertJsonPath('reponseAvant', null)
            ->assertJsonMissingPath('acompte')
            ->assertJsonMissingPath('commission')
            ->assertJsonMissingPath('montantCredite');
    });

    it('suit les taux configurés', function () {
        config(['sopi.paiement.acompte_taux' => 0.5, 'sopi.paiement.commission_taux' => 0.2]);
        $moi = prestataireDesComplements();
        $reservation = reservationA5000($moi, StatutReservation::Acceptee);

        $this->getJson(URL_RESERVATIONS_PRESTATAIRE.'/'.$reservation->id)->assertOk()
            ->assertJsonPath('acompte', 2500)
            ->assertJsonPath('commission', 1000)
            ->assertJsonPath('montantCredite', 1500);
    });

    it('préfère les montants figés du paiement réussi', function () {
        $moi = prestataireDesComplements();
        $reservation = reservationA5000($moi, StatutReservation::Confirmee);
        Paiement::factory()->reussi()->create(['reservation_id' => $reservation->id, 'montant_acompte' => '1800.00', 'commission' => '600.00']);

        $this->getJson(URL_RESERVATIONS_PRESTATAIRE.'/'.$reservation->id)->assertOk()
            ->assertJsonPath('acompte', 1800)
            ->assertJsonPath('commission', 600)
            ->assertJsonPath('montantCredite', 1200);
    });

    it('annonce exactement ce que CreditSoldeService crédite à la fin de la prestation', function () {
        $moi = prestataireDesComplements();
        $reservation = reservationA5000($moi, StatutReservation::EnCours);
        Paiement::factory()->reussi()->create(['reservation_id' => $reservation->id, 'montant_acompte' => '1500.00', 'commission' => '500.00']);
        $annonce = $this->getJson(URL_RESERVATIONS_PRESTATAIRE.'/'.$reservation->id)->assertOk()->json('montantCredite');

        $detail = app(CreditSoldeService::class)->crediterAvecDetail($reservation);

        expect($detail)->not->toBeNull()
            ->and((float) $detail->montantCredite)->toBe((float) $annonce)
            ->and((float) $annonce)->toBe(1000.0);
    });
});

describe('inchangé', function () {
    it('garde les champs existants et n\'expose aucune donnée personnelle du client', function () {
        $moi = prestataireDesComplements();
        $reservation = reservationA5000($moi, StatutReservation::Confirmee);

        $reponse = $this->getJson(URL_RESERVATIONS_PRESTATAIRE.'/'.$reservation->id)->assertOk()
            ->assertJsonStructure([
                'id', 'statut', 'dateCreation', 'motifRefus',
                'creneau' => ['id', 'dateDebut', 'dateFin'],
                'prestation' => ['id', 'nom', 'dureeMinutes', 'prix'],
                'client' => ['prenom', 'initialeNom'],
                'reponseAvant', 'acompte', 'commission', 'montantCredite',
            ]);
        expect(array_keys($reponse->json('client')))->toBe(['prenom', 'initialeNom'])
            ->and($reponse->getContent())->not->toContain($reservation->client->telephone)
            ->and($reponse->getContent())->not->toContain($reservation->client->email);
    });

    it('ne montre à un prestataire que ses réservations', function () {
        $moi = prestataireDesComplements();
        $autre = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
        $mienne = reservationA5000($moi, StatutReservation::EnAttente);
        $etrangere = reservationA5000($autre, StatutReservation::EnAttente);

        $this->getJson(URL_RESERVATIONS_PRESTATAIRE)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mienne->id);
        $this->getJson(URL_RESERVATIONS_PRESTATAIRE.'/'.$etrangere->id)->assertNotFound();
    });
});
