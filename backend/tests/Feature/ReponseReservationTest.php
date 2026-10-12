<?php

use App\Enums\StatutCreneau;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationAcceptee;
use App\Events\Reservation\ReservationRefusee;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

const URL_DEMANDES = '/api/v1/prestataire/reservations';

/** Prestataire actif connecté à l'API. */
function prestataireConnecte(): Prestataire
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    test()->actingAs($prestataire->user, 'sanctum');

    return $prestataire;
}

/** Demande sur un créneau RESERVE du prestataire. $attributs écrase les valeurs de la réservation. */
function demandeRecue(Prestataire $prestataire, array $attributs = [], ?User $client = null): Reservation
{
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create();

    return Reservation::factory()->create(array_merge([
        'client_id' => ($client ?? User::factory()->create(['prenom' => 'Awa', 'nom' => 'diop']))->id,
        'creneau_id' => $creneau->id,
    ], $attributs));
}

describe('accès', function () {
    it('renvoie 401 sans jeton, puis 403 pour un client et un administrateur', function () {
        $demande = demandeRecue(Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create());
        $routes = [
            ['getJson', URL_DEMANDES], ['getJson', URL_DEMANDES.'/'.$demande->id],
            ['postJson', URL_DEMANDES.'/'.$demande->id.'/accepter'],
            ['postJson', URL_DEMANDES.'/'.$demande->id.'/refuser'],
        ];

        foreach ($routes as [$methode, $url]) {
            $this->$methode($url)->assertUnauthorized();
        }
        foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum');
            foreach ($routes as [$methode, $url]) {
                $this->$methode($url)->assertForbidden();
            }
        }
        expect($demande->fresh()->statut)->toBe(StatutReservation::EnAttente);
    });
});

describe('liste', function () {
    it('ne montre que les demandes du prestataire connecté, les plus récentes d\'abord', function () {
        $moi = prestataireConnecte();
        $autre = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
        $ancienne = demandeRecue($moi, ['created_at' => now()->subHours(3)]);
        $recente = demandeRecue($moi, ['created_at' => now()->subHour()]);
        demandeRecue($autre);

        $this->getJson(URL_DEMANDES)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $recente->id)
            ->assertJsonPath('data.1.id', $ancienne->id)
            ->assertJsonPath('meta.total', 2);
    });

    it('pagine par 20', function () {
        $moi = prestataireConnecte();
        foreach (range(1, 21) as $i) {
            demandeRecue($moi);
        }

        $this->getJson(URL_DEMANDES)->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson(URL_DEMANDES.'?page=2')->assertOk()->assertJsonCount(1, 'data');
    });

    it('filtre par statut valide', function () {
        $moi = prestataireConnecte();
        demandeRecue($moi);
        $acceptee = demandeRecue($moi, ['statut' => StatutReservation::Acceptee, 'acceptee_le' => now()]);

        $this->getJson(URL_DEMANDES.'?statut=acceptee')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $acceptee->id);
    });

    it('refuse un statut inconnu avec 422', function () {
        prestataireConnecte();

        $this->getJson(URL_DEMANDES.'?statut=nimporte')->assertStatus(422)
            ->assertJsonValidationErrors('statut')
            ->assertJsonPath('errors.statut.0', fn ($m) => str_starts_with($m, 'Le statut doit être l\'un de'));
    });
});

describe('détail', function () {
    it('renvoie la demande avec le client réduit au prénom et à l\'initiale', function () {
        $moi = prestataireConnecte();
        $demande = demandeRecue($moi);

        $this->getJson(URL_DEMANDES.'/'.$demande->id)->assertOk()
            ->assertJsonPath('id', $demande->id)
            ->assertJsonPath('statut', 'en_attente')
            ->assertJsonPath('motifRefus', null)
            ->assertJsonPath('creneau.id', $demande->creneau_id)
            ->assertJsonPath('prestation.id', $demande->prestation_id)
            ->assertJsonPath('client', ['prenom' => 'Awa', 'initialeNom' => 'D'])
            ->assertJsonStructure(['id', 'statut', 'dateCreation', 'motifRefus', 'creneau' => ['id', 'dateDebut', 'dateFin'], 'prestation' => ['id', 'nom', 'dureeMinutes', 'prix'], 'client' => ['prenom', 'initialeNom']]);
    });

    it('répond 404 pour la réservation d\'un autre prestataire, comme pour une inconnue', function () {
        prestataireConnecte();
        $autre = demandeRecue(Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create());

        $this->getJson(URL_DEMANDES.'/'.$autre->id)->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
        $this->getJson(URL_DEMANDES.'/'.fake()->uuid())->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
        $this->getJson(URL_DEMANDES.'/pas-un-uuid')->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
    });

    it('n\'expose ni données personnelles du client ni code de réservation', function () {
        $moi = prestataireConnecte();
        $client = User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diop']);
        $demande = demandeRecue($moi, ['statut' => StatutReservation::Confirmee, 'code_reservation' => 'ZX81QK'], $client);

        foreach ([URL_DEMANDES, URL_DEMANDES.'/'.$demande->id] as $url) {
            $corps = $this->getJson($url)->assertOk()->getContent();
            foreach ([$client->telephone, $client->email, $client->id, 'Diop', 'ZX81QK', 'code'] as $interdit) {
                if ($interdit !== null) {
                    expect($corps)->not->toContain($interdit);
                }
            }
        }
    });
});

describe('acceptation', function () {
    it('passe la demande à ACCEPTEE, horodate acceptee_le et garde le créneau réservé', function () {
        Event::fake([ReservationAcceptee::class]);
        $moi = prestataireConnecte();
        $demande = demandeRecue($moi);

        $this->postJson(URL_DEMANDES.'/'.$demande->id.'/accepter')->assertOk()->assertJsonPath('statut', 'acceptee');

        $demande->refresh();
        expect($demande->statut)->toBe(StatutReservation::Acceptee)
            ->and($demande->acceptee_le)->not->toBeNull()
            ->and($demande->creneau->statut)->toBe(StatutCreneau::Reserve);
        Event::assertDispatched(ReservationAcceptee::class, fn ($e) => $e->reservation->is($demande));
    });

    it('répond 404 pour la demande d\'un autre prestataire sans la modifier', function () {
        prestataireConnecte();
        $autre = demandeRecue(Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create());

        $this->postJson(URL_DEMANDES.'/'.$autre->id.'/accepter')->assertNotFound();
        $this->postJson(URL_DEMANDES.'/'.$autre->id.'/refuser', ['motif' => 'Indisponible'])->assertNotFound();
        expect($autre->fresh()->statut)->toBe(StatutReservation::EnAttente);
    });
});

describe('refus', function () {
    it('passe la demande à REFUSEE avec le motif et libère le créneau', function () {
        Event::fake([ReservationRefusee::class]);
        $moi = prestataireConnecte();
        $demande = demandeRecue($moi);

        $this->postJson(URL_DEMANDES.'/'.$demande->id.'/refuser', ['motif' => 'Je suis absent ce jour-là.'])->assertOk()
            ->assertJsonPath('statut', 'refusee')
            ->assertJsonPath('motifRefus', 'Je suis absent ce jour-là.');

        $creneau = $demande->creneau->fresh();
        expect($demande->fresh()->statut)->toBe(StatutReservation::Refusee)
            ->and($demande->fresh()->motif_refus)->toBe('Je suis absent ce jour-là.')
            ->and($creneau->statut)->toBe(StatutCreneau::Libre)
            ->and($creneau->verrou_expire_a)->toBeNull()
            ->and($creneau->verrouille_par)->toBeNull();
        Event::assertDispatched(ReservationRefusee::class, fn ($e) => $e->reservation->is($demande));
    });

    it('valide le motif : absent, trop court, trop long', function (array $corps, string $message) {
        $moi = prestataireConnecte();
        $demande = demandeRecue($moi);

        $this->postJson(URL_DEMANDES.'/'.$demande->id.'/refuser', $corps)->assertStatus(422)
            ->assertJsonPath('errors.motif.0', $message);
        expect($demande->fresh()->statut)->toBe(StatutReservation::EnAttente);
    })->with([
        'absent' => [[], 'Le motif du refus est obligatoire.'],
        'trop court' => [['motif' => 'ab'], 'Le motif du refus doit contenir au moins 3 caractères.'],
        'trop long' => [['motif' => str_repeat('a', 501)], 'Le motif du refus ne doit pas dépasser 500 caractères.'],
    ]);

    it('est atomique : si la libération du créneau échoue, la demande reste EN_ATTENTE', function () {
        Event::fake([ReservationRefusee::class]);
        $moi = prestataireConnecte();
        $demande = demandeRecue($moi);
        // Le créneau n'est plus réservé : la libération conditionnelle échoue après la mise à jour de la demande.
        $demande->creneau->forceFill(['statut' => StatutCreneau::Indisponible])->save();

        $this->postJson(URL_DEMANDES.'/'.$demande->id.'/refuser', ['motif' => 'Indisponible'])->assertStatus(409);

        expect($demande->fresh()->statut)->toBe(StatutReservation::EnAttente)
            ->and($demande->fresh()->motif_refus)->toBeNull();
        Event::assertNotDispatched(ReservationRefusee::class);
    });
});

describe('règles de réponse', function () {
    it('répond 409 à une seconde réponse (accepter puis refuser, et inversement)', function () {
        $moi = prestataireConnecte();
        $acceptee = demandeRecue($moi);
        $refusee = demandeRecue($moi);

        $this->postJson(URL_DEMANDES.'/'.$acceptee->id.'/accepter')->assertOk();
        $this->postJson(URL_DEMANDES.'/'.$acceptee->id.'/refuser', ['motif' => 'Finalement non'])->assertStatus(409)
            ->assertExactJson(['message' => 'Cette demande a déjà reçu une réponse.']);
        $this->postJson(URL_DEMANDES.'/'.$acceptee->id.'/accepter')->assertStatus(409);

        $this->postJson(URL_DEMANDES.'/'.$refusee->id.'/refuser', ['motif' => 'Indisponible'])->assertOk();
        $this->postJson(URL_DEMANDES.'/'.$refusee->id.'/accepter')->assertStatus(409)
            ->assertExactJson(['message' => 'Cette demande a déjà reçu une réponse.']);

        expect($acceptee->fresh()->statut)->toBe(StatutReservation::Acceptee)
            ->and($refusee->fresh()->statut)->toBe(StatutReservation::Refusee);
    });

    it('répond 409 quand la demande a plus de 12 h, pour accepter comme pour refuser', function () {
        Event::fake([ReservationAcceptee::class, ReservationRefusee::class]);
        $moi = prestataireConnecte();
        $demande = demandeRecue($moi, ['created_at' => now()->subHours(12)->subMinute()]);

        $this->postJson(URL_DEMANDES.'/'.$demande->id.'/accepter')->assertStatus(409)
            ->assertExactJson(['message' => 'Le délai de réponse est dépassé.']);
        $this->postJson(URL_DEMANDES.'/'.$demande->id.'/refuser', ['motif' => 'Trop tard'])->assertStatus(409)
            ->assertExactJson(['message' => 'Le délai de réponse est dépassé.']);

        expect($demande->fresh()->statut)->toBe(StatutReservation::EnAttente)
            ->and($demande->creneau->fresh()->statut)->toBe(StatutCreneau::Reserve);
        Event::assertNothingDispatched();
    });

    it('accepte encore une demande de 11 h', function () {
        $moi = prestataireConnecte();
        $demande = demandeRecue($moi, ['created_at' => now()->subHours(11)]);

        $this->postJson(URL_DEMANDES.'/'.$demande->id.'/accepter')->assertOk();
    });

    it('utilise le délai configuré', function () {
        config(['sopi.reservations.delai_reponse_heures' => 1]);
        $moi = prestataireConnecte();
        $demande = demandeRecue($moi, ['created_at' => now()->subHours(2)]);

        $this->postJson(URL_DEMANDES.'/'.$demande->id.'/accepter')->assertStatus(409);
    });
});

describe('limitation de débit', function () {
    it('limite les réponses à 30 par minute et par utilisateur', function () {
        prestataireConnecte();

        foreach (range(1, 30) as $i) {
            $this->postJson(URL_DEMANDES.'/'.fake()->uuid().'/accepter')->assertNotFound();
        }
        $this->postJson(URL_DEMANDES.'/'.fake()->uuid().'/accepter')->assertStatus(429);
        $this->postJson(URL_DEMANDES.'/'.fake()->uuid().'/refuser', ['motif' => 'Indisponible'])->assertStatus(429);
    });
});
