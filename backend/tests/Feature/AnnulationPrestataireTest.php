<?php

use App\Enums\CauseAnnulation;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Enums\StatutCreneau;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationAnnulee;
use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Paiement\FakePaiementService;
use App\Services\Paiement\PaiementService;
use App\Services\Paiement\ResultatOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

const SECRET_ANNULATION_TEST = 'secret-factice-annulation';
const MOTIF_ANNULATION = "Un imprévu m'empêche de venir.";

beforeEach(function () {
    Http::preventStrayRequests();
    config(['sopi.paiement.driver' => 'fake', 'sopi.paiement.webhook_secret' => SECRET_ANNULATION_TEST]);
    app()->forgetInstance(PaiementService::class);
    reglageRemboursement()->exchangeArray(['appels' => [], 'echec_total' => false, 'references_en_echec' => []]);
});

/** État partagé du faux prestataire : appels reçus, échec général, références dont le remboursement échoue. */
function reglageRemboursement(): ArrayObject
{
    static $reglage;

    return $reglage ??= new ArrayObject;
}

/** Faux prestataire de paiement espionné : $succes décide de l'issue de chaque remboursement (par défaut). */
function espionAnnulation(bool $succes = true): MockInterface
{
    $espion = Mockery::mock(FakePaiementService::class, [SECRET_ANNULATION_TEST])->makePartial();
    reglageRemboursement()['echec_total'] = ! $succes;
    $espion->shouldReceive('rembourser')->andReturnUsing(function (string $reference, string $montant) {
        $reglage = reglageRemboursement();
        $reglage['appels'] = [...$reglage['appels'], [$reference, $montant]];
        $echec = $reglage['echec_total'] || in_array($reference, $reglage['references_en_echec'], true);

        return new ResultatOperation(! $echec, $echec ? null : 'RMB-TEST');
    })->byDefault();
    app()->instance(PaiementService::class, $espion);

    return $espion;
}

/** Prestataire actif connecté. */
function prestataireQuiAnnule(): Prestataire
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire()->statut(StatutCompte::Actif), 'user')->create();
    test()->actingAs($prestataire->user, 'sanctum');

    return $prestataire;
}

/** Réservation du statut demandé (état de la factory) sur un créneau RESERVE débutant dans $minutes minutes. */
function reservationPourAnnulation(Prestataire $prestataire, string $etat = 'acceptee', ?string $paiement = null, int $minutes = 2880): Reservation
{
    $debut = now()->addMinutes($minutes);
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
    $reservation = Reservation::factory()->{$etat}()->create(['client_id' => User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diop'])->id, 'creneau_id' => $creneau->id]);
    if ($paiement !== null) {
        Paiement::factory()->{$paiement}()->create(['reservation_id' => $reservation->id, 'montant_acompte' => '4500.00']);
    }

    return $reservation;
}

function urlAnnulation(Reservation|string $reservation): string
{
    return '/api/v1/prestataire/reservations/'.($reservation instanceof Reservation ? $reservation->id : $reservation).'/annuler';
}

function plageIndisponible(): array
{
    return ['debut' => now()->addDays(2)->format('Y-m-d').' 08:00', 'fin' => now()->addDays(2)->format('Y-m-d').' 18:00'];
}

function reservationDansLaPlage(Prestataire $p, string $etat, ?string $paiement, string $heure = '09:00'): Reservation
{
    $debut = now()->addDays(2)->setTimeFromTimeString($heure);
    $creneau = Creneau::factory()->for($p)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
    $reservation = Reservation::factory()->{$etat}()->create(['client_id' => User::factory()->create()->id, 'creneau_id' => $creneau->id]);
    if ($paiement !== null) {
        Paiement::factory()->{$paiement}()->create(['reservation_id' => $reservation->id, 'montant_acompte' => '4500.00']);
    }

    return $reservation;
}

describe('annulation par le prestataire', function () {
    it('annule une réservation ACCEPTEE sans paiement : créneau libre, motif visible du client, aucun remboursement', function () {
        $espion = espionAnnulation();
        $espion->shouldNotReceive('rembourser');
        Event::fake([ReservationAnnulee::class]);
        $prestataire = prestataireQuiAnnule();
        $reservation = reservationPourAnnulation($prestataire, 'acceptee');

        $this->postJson(urlAnnulation($reservation), ['motif' => MOTIF_ANNULATION])
            ->assertOk()
            ->assertJsonPath('statut', 'annulee')
            ->assertJsonPath('motifRefus', MOTIF_ANNULATION)
            ->assertJsonMissingPath('paiement')
            ->assertJsonMissingPath('commission');

        expect($reservation->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($reservation->creneau->fresh()->statut)->toBe(StatutCreneau::Libre);
        Event::assertDispatched(ReservationAnnulee::class, fn ($e) => $e->par === Role::Prestataire && $e->cause === CauseAnnulation::Demande && $e->reservation->is($reservation));

        $this->actingAs($reservation->client, 'sanctum');
        $this->getJson('/api/v1/reservations/'.$reservation->id)->assertOk()->assertJsonPath('motifRefus', MOTIF_ANNULATION)->assertJsonPath('paiement', null);
    });

    it('annule une réservation CONFIRMEE payée : créneau libre, acompte intégralement remboursé, paiement REMBOURSE', function () {
        $espion = espionAnnulation();
        Event::fake([ReservationAnnulee::class]);
        $prestataire = prestataireQuiAnnule();
        $reservation = reservationPourAnnulation($prestataire, 'confirmee', 'reussi');
        $paiement = $reservation->paiements()->firstOrFail();

        $this->postJson(urlAnnulation($reservation), ['motif' => MOTIF_ANNULATION])->assertOk()->assertJsonPath('statut', 'annulee');

        expect(reglageRemboursement()['appels'])->toBe([[$paiement->reference_dexpay, '4500.00']]);
        $paiement->refresh();
        expect($paiement->statut)->toBe(StatutPaiement::Rembourse)
            ->and($paiement->rembourse_le)->not->toBeNull()
            ->and($paiement->remboursement_du_le)->not->toBeNull()
            ->and($reservation->creneau->fresh()->statut)->toBe(StatutCreneau::Libre);
        Event::assertDispatched(ReservationAnnulee::class, fn ($e) => $e->par === Role::Prestataire);

        $this->actingAs($reservation->client, 'sanctum');
        $this->getJson('/api/v1/reservations/'.$reservation->id)->assertOk()
            ->assertJsonPath('paiement', ['statut' => 'rembourse', 'montantAcompte' => 4500]);
    });

    it('répond 200 quand le remboursement échoue : paiement REUSSI, remboursement dû, repris ensuite par la commande', function () {
        espionAnnulation(false);
        $prestataire = prestataireQuiAnnule();
        $reservation = reservationPourAnnulation($prestataire, 'confirmee', 'reussi');
        $paiement = $reservation->paiements()->firstOrFail();

        $this->postJson(urlAnnulation($reservation), ['motif' => MOTIF_ANNULATION])->assertOk()->assertJsonPath('statut', 'annulee');

        $paiement->refresh();
        expect($paiement->statut)->toBe(StatutPaiement::Reussi)->and($paiement->remboursement_du_le)->not->toBeNull()
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Annulee);

        $this->actingAs($reservation->client, 'sanctum');
        $this->getJson('/api/v1/reservations/'.$reservation->id)->assertJsonPath('paiement.statut', 'reussi');

        reglageRemboursement()['echec_total'] = false;
        $this->travel(10)->minutes();
        $this->artisan('sopi:paiements:rembourser-dus')->assertSuccessful();
        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Rembourse);
    });

    it('refuse une demande EN_ATTENTE en renvoyant vers le refus', function () {
        $prestataire = prestataireQuiAnnule();
        $reservation = reservationPourAnnulation($prestataire, 'enAttente');

        $this->postJson(urlAnnulation($reservation), ['motif' => MOTIF_ANNULATION])
            ->assertStatus(409)->assertExactJson(['message' => 'Pour une demande en attente, utilisez le refus.']);
        expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente);
    });

    it('refuse les autres statuts avec 409 sans rien modifier', function (string $etat) {
        $prestataire = prestataireQuiAnnule();
        $reservation = reservationPourAnnulation($prestataire, $etat);
        $avant = $reservation->fresh()->statut;

        $this->postJson(urlAnnulation($reservation), ['motif' => MOTIF_ANNULATION])
            ->assertStatus(409)->assertExactJson(['message' => 'Cette réservation ne peut plus être annulée.']);
        expect($reservation->fresh()->statut)->toBe($avant)->and($reservation->fresh()->motif_refus)->toBe($avant === StatutReservation::Refusee ? $reservation->motif_refus : null);
    })->with(['enCours', 'terminee', 'refusee', 'annulee']);

    it('refuse un créneau déjà commencé avec 409 et ne touche à rien', function (string $etat) {
        $espion = espionAnnulation();
        $espion->shouldNotReceive('rembourser');
        $prestataire = prestataireQuiAnnule();
        $reservation = reservationPourAnnulation($prestataire, $etat, 'reussi', -60);

        $this->postJson(urlAnnulation($reservation), ['motif' => MOTIF_ANNULATION])
            ->assertStatus(409)->assertExactJson(['message' => 'Le créneau a déjà commencé.']);
        expect($reservation->fresh()->statut->value)->toBe($reservation->statut->value)
            ->and($reservation->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve)
            ->and($reservation->paiements()->first()->remboursement_du_le)->toBeNull();
    })->with(['acceptee', 'confirmee']);

    it('valide le motif : absent, trop court, trop long (422)', function (mixed $motif, string $message) {
        $prestataire = prestataireQuiAnnule();
        $reservation = reservationPourAnnulation($prestataire, 'acceptee');

        $this->postJson(urlAnnulation($reservation), $motif === null ? [] : ['motif' => $motif])
            ->assertStatus(422)->assertJsonPath('errors.motif.0', $message);
        expect($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee);
    })->with([
        'absent' => [null, "Le motif de l'annulation est obligatoire."],
        'court' => ['ab', "Le motif de l'annulation doit contenir au moins 3 caractères."],
        'long' => [str_repeat('a', 501), "Le motif de l'annulation ne doit pas dépasser 500 caractères."],
    ]);

    it("répond 404 pour la réservation d'un autre prestataire, une réservation inconnue ou un identifiant mal formé", function () {
        prestataireQuiAnnule();
        $autre = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
        $reservation = reservationPourAnnulation($autre, 'acceptee');

        foreach ([$reservation->id, '0198a1b2-0000-7000-8000-00000000ffff', 'pas-un-uuid'] as $id) {
            $this->postJson(urlAnnulation($id), ['motif' => MOTIF_ANNULATION])->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
        }
        expect($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee);
    });

    it('renvoie 401 sans jeton, puis 403 pour un client et un administrateur', function () {
        $reservation = reservationPourAnnulation(Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create(), 'acceptee');

        $this->postJson(urlAnnulation($reservation), ['motif' => MOTIF_ANNULATION])->assertUnauthorized();
        foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum');
            $this->postJson(urlAnnulation($reservation), ['motif' => MOTIF_ANNULATION])->assertForbidden();
        }
        expect($reservation->fresh()->statut)->toBe(StatutReservation::Acceptee);
    });

    it("est atomique : si la libération du créneau échoue, rien ne change et aucun événement n'est publié", function () {
        $espion = espionAnnulation();
        $espion->shouldNotReceive('rembourser');
        Event::fake([ReservationAnnulee::class]);
        $prestataire = prestataireQuiAnnule();
        $reservation = reservationPourAnnulation($prestataire, 'confirmee', 'reussi');
        // Le créneau n'est plus « réservé » : la libération conditionnelle ne modifie aucune ligne.
        $reservation->creneau->forceFill(['statut' => StatutCreneau::Indisponible])->save();

        $this->postJson(urlAnnulation($reservation), ['motif' => MOTIF_ANNULATION])->assertStatus(409);

        expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee)
            ->and($reservation->fresh()->motif_refus)->toBeNull()
            ->and($reservation->paiements()->first()->remboursement_du_le)->toBeNull()
            ->and($reservation->paiements()->first()->statut)->toBe(StatutPaiement::Reussi);
        Event::assertNotDispatched(ReservationAnnulee::class);
    });

    it('ne révèle ni commission, ni référence de paiement, ni identité complète du client', function () {
        espionAnnulation();
        $prestataire = prestataireQuiAnnule();
        $reservation = reservationPourAnnulation($prestataire, 'confirmee', 'reussi');
        $reference = $reservation->paiements()->first()->reference_dexpay;

        $json = $this->postJson(urlAnnulation($reservation), ['motif' => MOTIF_ANNULATION])->assertOk()->getContent();

        expect($json)->not->toContain($reference)->not->toContain('commission')->not->toContain('Diop')->not->toContain($reservation->client->telephone);
    });
});

describe('annulation par le client', function () {
    it('ne rembourse rien et ne marque rien : le paiement REUSSI reste inchangé', function () {
        $espion = espionAnnulation();
        $espion->shouldNotReceive('rembourser');
        $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
        $reservation = reservationPourAnnulation($prestataire, 'confirmee', 'reussi', 2880);
        $this->actingAs($reservation->client, 'sanctum');

        $this->postJson('/api/v1/reservations/'.$reservation->id.'/annuler')->assertOk()->assertJsonPath('statut', 'annulee');

        $paiement = $reservation->paiements()->firstOrFail();
        expect($paiement->statut)->toBe(StatutPaiement::Reussi)
            ->and($paiement->rembourse_le)->toBeNull()
            ->and($paiement->remboursement_du_le)->toBeNull()
            ->and($paiement->remboursement_tentatives)->toBe(0);
    });
});

describe('indisponibilité et remboursement', function () {
    it("rembourse l'acompte d'une réservation CONFIRMEE payée", function () {
        $espion = espionAnnulation();
        $p = prestataireQuiAnnule();
        $reservation = reservationDansLaPlage($p, 'confirmee', 'reussi');
        $paiement = $reservation->paiements()->firstOrFail();

        $this->postJson('/api/v1/prestataire/agenda/indisponibilites', plageIndisponible())->assertOk()->assertJsonPath('reservationsAnnulees', 1);

        expect(reglageRemboursement()['appels'])->toBe([[$paiement->reference_dexpay, '4500.00']]);
        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Rembourse)
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($reservation->creneau->fresh()->statut)->toBe(StatutCreneau::Indisponible);
    });

    it('ne touche pas un paiement EN_ATTENTE', function () {
        $espion = espionAnnulation();
        $espion->shouldNotReceive('rembourser');
        $p = prestataireQuiAnnule();
        $reservation = reservationDansLaPlage($p, 'acceptee', 'enAttente');

        $this->postJson('/api/v1/prestataire/agenda/indisponibilites', plageIndisponible())->assertOk();

        $paiement = $reservation->paiements()->firstOrFail();
        expect($paiement->statut)->toBe(StatutPaiement::EnAttente)->and($paiement->remboursement_du_le)->toBeNull()
            ->and($reservation->fresh()->statut)->toBe(StatutReservation::Annulee);
    });

    it("réussit même si un remboursement échoue ; l'autre est remboursé et l'échec est repris ensuite", function () {
        $espion = espionAnnulation();
        $p = prestataireQuiAnnule();
        $a = reservationDansLaPlage($p, 'confirmee', 'reussi', '09:00');
        $b = reservationDansLaPlage($p, 'confirmee', 'reussi', '11:00');
        $referenceEnEchec = $a->paiements()->first()->reference_dexpay;
        reglageRemboursement()['references_en_echec'] = [$referenceEnEchec];

        $this->postJson('/api/v1/prestataire/agenda/indisponibilites', plageIndisponible())->assertOk()->assertJsonPath('reservationsAnnulees', 2);

        expect($a->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($a->paiements()->first()->statut)->toBe(StatutPaiement::Reussi)
            ->and($a->paiements()->first()->remboursement_du_le)->not->toBeNull()
            ->and($b->paiements()->first()->statut)->toBe(StatutPaiement::Rembourse);

        reglageRemboursement()['references_en_echec'] = [];
        $this->travel(10)->minutes();
        $this->artisan('sopi:paiements:rembourser-dus')->assertSuccessful();
        expect($a->paiements()->first()->statut)->toBe(StatutPaiement::Rembourse);
    });
});

describe('clé paiement côté client', function () {
    it('expose seulement statut et montantAcompte du paiement le plus récent', function () {
        $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
        $reservation = reservationPourAnnulation($prestataire, 'acceptee', 'echoue');
        $this->travel(1)->minutes();
        $recent = Paiement::factory()->reussi()->create(['reservation_id' => $reservation->id, 'montant_acompte' => '4500.00']);
        $this->actingAs($reservation->client, 'sanctum');

        $reponse = $this->getJson('/api/v1/reservations/'.$reservation->id)->assertOk()->assertJsonPath('paiement', ['statut' => 'reussi', 'montantAcompte' => 4500]);

        expect($reponse->getContent())->not->toContain($recent->reference_dexpay)->not->toContain('commission')->not->toContain('urlPaiement');
        $this->getJson('/api/v1/reservations')->assertOk()->assertJsonPath('data.0.paiement.statut', 'reussi');
    });
});
