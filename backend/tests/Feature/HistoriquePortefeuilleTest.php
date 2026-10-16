<?php

use App\Enums\StatutRetrait;
use App\Enums\TypeMouvementPortefeuille;
use App\Models\Creneau;
use App\Models\MouvementPortefeuille;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\Retrait;
use App\Models\User;
use App\Services\RetraitService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function prestataireAvecSolde(int $solde): Prestataire
{
    return Prestataire::factory()->avoirSolde($solde)->for(User::factory()->prestataire(), 'user')->create();
}

/** Réservation EN_COURS payée (acompte 4 500, commission 1 500 : crédit de 3 000). */
function enCoursPayee(Prestataire $prestataire): Reservation
{
    $reservation = Reservation::factory()->enCours()->create([
        'client_id' => User::factory()->create()->id,
        'creneau_id' => Creneau::factory()->for($prestataire)->reserve()->create()->id,
        'prix_applique' => '15000.00',
    ]);
    Paiement::factory()->reussi()->create(['reservation_id' => $reservation->id, 'montant_acompte' => '4500.00', 'commission' => '1500.00']);

    return $reservation;
}

describe('alimentation du journal', function () {
    it('écrit un crédit avec le solde après quand la prestation est terminée, une seule fois', function () {
        $prestataire = prestataireAvecSolde(2000);
        $this->actingAs($prestataire->user, 'sanctum');
        $reservation = enCoursPayee($prestataire);

        $this->postJson("/api/v1/prestataire/reservations/{$reservation->id}/terminer")->assertOk();
        $this->postJson("/api/v1/prestataire/reservations/{$reservation->id}/terminer")->assertStatus(409);

        $mouvement = MouvementPortefeuille::query()->sole();
        expect($mouvement->type)->toBe(TypeMouvementPortefeuille::Credit)
            ->and((float) $mouvement->montant)->toBe(3000.0)
            ->and((float) $mouvement->solde_apres)->toBe(5000.0)
            ->and($mouvement->reservation_id)->toBe($reservation->id)
            ->and($mouvement->prestataire_id)->toBe($prestataire->id);
    });

    it('écrit un mouvement retrait avec le solde après quand un retrait est demandé', function () {
        $prestataire = prestataireAvecSolde(20000);
        $this->actingAs($prestataire->user, 'sanctum');

        $this->postJson('/api/v1/prestataire/retraits', ['montant' => 5000, 'telephone_versement' => '771234567', 'operateur' => 'wave'])->assertCreated();

        $mouvement = MouvementPortefeuille::query()->sole();
        expect($mouvement->type)->toBe(TypeMouvementPortefeuille::Retrait)
            ->and((float) $mouvement->montant)->toBe(5000.0)
            ->and((float) $mouvement->solde_apres)->toBe(15000.0)
            ->and($mouvement->retrait_id)->toBe(Retrait::query()->sole()->id);
    });

    it('n\'écrit rien quand le solde est insuffisant', function () {
        $prestataire = prestataireAvecSolde(1500);
        $this->actingAs($prestataire->user, 'sanctum');

        $this->postJson('/api/v1/prestataire/retraits', ['montant' => 5000, 'telephone_versement' => '771234567', 'operateur' => 'wave'])->assertUnprocessable();

        expect(MouvementPortefeuille::query()->count())->toBe(0);
    });

    it('écrit un remboursement quand un retrait échoue, une seule fois même si le résultat est rejoué', function () {
        $prestataire = prestataireAvecSolde(15000);
        $retrait = Retrait::factory()->enCours()->create(['prestataire_id' => $prestataire->id, 'montant' => '5000.00']);

        $service = app(RetraitService::class);
        expect($service->appliquerResultat($retrait, StatutRetrait::Echoue))->toBeTrue();
        $service->appliquerResultat($retrait->fresh(), StatutRetrait::Echoue);

        $mouvement = MouvementPortefeuille::query()->sole();
        expect($mouvement->type)->toBe(TypeMouvementPortefeuille::RemboursementRetrait)
            ->and((float) $mouvement->montant)->toBe(5000.0)
            ->and((float) $mouvement->solde_apres)->toBe(20000.0)
            ->and($mouvement->retrait_id)->toBe($retrait->id)
            ->and((float) $prestataire->fresh()->solde)->toBe(20000.0);
    });
});

describe('GET /prestataire/portefeuille/mouvements', function () {
    it('liste du plus récent au plus ancien, paginé par 20, limité au prestataire connecté', function () {
        $prestataire = prestataireAvecSolde(0);
        $autre = prestataireAvecSolde(0);
        foreach (range(1, 22) as $i) {
            MouvementPortefeuille::factory()->create(['prestataire_id' => $prestataire->id, 'libelle' => "M$i", 'created_at' => now()->subMinutes(30 - $i)]);
        }
        MouvementPortefeuille::factory()->create(['prestataire_id' => $autre->id, 'libelle' => 'Autre']);
        $this->actingAs($prestataire->user, 'sanctum');

        $page1 = $this->getJson('/api/v1/prestataire/portefeuille/mouvements')->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 22)
            ->assertJsonPath('data.0.libelle', 'M22');
        expect(array_keys($page1->json('data.0')))->toBe(['id', 'type', 'montant', 'libelle', 'soldeApres', 'reservationId', 'retraitId', 'date']);

        $this->getJson('/api/v1/prestataire/portefeuille/mouvements?page=2')->assertOk()->assertJsonCount(2, 'data');
    });

    it('exige un prestataire : 401 sans jeton, 403 pour un client', function () {
        $this->getJson('/api/v1/prestataire/portefeuille/mouvements')->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson('/api/v1/prestataire/portefeuille/mouvements')->assertForbidden();
    });
});

/** Prestataire dont le passé n'a jamais été journalisé : +3 000, −1 000, −500 puis +500 (retrait échoué recrédité) = 2 000. */
function passeSansJournal(): Prestataire
{
    $prestataire = prestataireAvecSolde(0);
    $reservation = enCoursPayee($prestataire);
    Paiement::query()->where('reservation_id', $reservation->id)->update(['credite_le' => now()->subDays(3)]);
    Retrait::factory()->reussi()->create(['prestataire_id' => $prestataire->id, 'montant' => '1000.00', 'created_at' => now()->subDays(2)]);
    Retrait::factory()->echoue()->create(['prestataire_id' => $prestataire->id, 'montant' => '500.00', 'created_at' => now()->subDay(), 'recredite_le' => now()->subDay()]);
    $prestataire->forceFill(['solde' => '2000.00'])->save();

    return $prestataire;
}

describe('sopi:portefeuille:reconstituer-historique', function () {
    it('ne crée rien en simulation', function () {
        passeSansJournal();

        $this->artisan('sopi:portefeuille:reconstituer-historique --dry-run')->expectsOutputToContain('4 mouvement(s) à créer')->assertSuccessful();

        expect(MouvementPortefeuille::query()->count())->toBe(0);
    });

    it('recrée les mouvements passés avec les bons soldes, puis ne duplique rien au second passage', function () {
        $prestataire = passeSansJournal();

        $this->artisan('sopi:portefeuille:reconstituer-historique')->expectsOutputToContain('4 mouvement(s) créé(s)')->assertSuccessful();
        $this->artisan('sopi:portefeuille:reconstituer-historique')->expectsOutputToContain('0 mouvement(s) créé(s)')->assertSuccessful();

        $mouvements = MouvementPortefeuille::query()->where('prestataire_id', $prestataire->id)->orderBy('created_at')->get();
        expect($mouvements->map(fn ($m) => $m->type->value)->all())->toBe(['credit', 'retrait', 'retrait', 'remboursement_retrait'])
            ->and($mouvements->map(fn ($m) => (float) $m->solde_apres)->all())->toBe([3000.0, 2000.0, 1500.0, 2000.0])
            ->and((float) $prestataire->fresh()->solde)->toBe(2000.0);
    });

    it('ne recrée pas un mouvement déjà journalisé', function () {
        $prestataire = prestataireAvecSolde(0);
        $reservation = enCoursPayee($prestataire);
        $this->actingAs($prestataire->user, 'sanctum');
        $this->postJson("/api/v1/prestataire/reservations/{$reservation->id}/terminer")->assertOk();

        $this->artisan('sopi:portefeuille:reconstituer-historique')->expectsOutputToContain('0 mouvement(s) créé(s)')->assertSuccessful();

        expect(MouvementPortefeuille::query()->count())->toBe(1);
    });
});
