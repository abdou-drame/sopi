<?php

use App\Enums\DecisionLitige;
use App\Enums\StatutCompte;
use App\Enums\StatutCreneau;
use App\Enums\StatutLitige;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Events\SanctionLevee;
use App\Events\SanctionPosee;
use App\Models\Creneau;
use App\Models\Litige;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\Sanction;
use App\Models\User;
use App\Services\Paiement\PaiementService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sopi.paiement.driver' => 'fake', 'sopi.paiement.webhook_secret' => 'secret-factice-sanctions']);
    app()->forgetInstance(PaiementService::class);
    RateLimiter::clear('sanction');
});

function prestataireSanctionnable(): Prestataire
{
    return Prestataire::factory()->for(User::factory()->prestataire()->statut(StatutCompte::Actif), 'user')->create();
}

function corpsSanction(string $type = 'avertissement', array $en_plus = []): array
{
    return array_merge(['type' => $type, 'motif' => 'Comportement injurieux envers un client.'], $en_plus);
}

function urlSanctions(User $u): string
{
    return '/api/v1/admin/utilisateurs/'.$u->id.'/sanctions';
}

/** Réservation de l'état donné sur un créneau RESERVE du prestataire, avec éventuellement un acompte payé. */
function reservationSanction(Prestataire $p, string $etat, bool $payee = false, ?User $client = null): Reservation
{
    $debut = now()->addDays(2)->addMinutes(random_int(1, 5000));
    $creneau = Creneau::factory()->reserve()->for($p)->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
    $reservation = Reservation::factory()->{$etat}()->create(['client_id' => ($client ?? User::factory()->create())->id, 'creneau_id' => $creneau->id]);
    if ($payee) {
        Paiement::factory()->reussi()->create(['reservation_id' => $reservation->id]);
    }

    return $reservation;
}

describe('autorisations et validation', function () {
    it('exige un jeton puis le rôle administrateur', function () {
        $cible = User::factory()->create();

        $this->postJson(urlSanctions($cible), corpsSanction())->assertUnauthorized();
        $this->getJson(urlSanctions($cible))->assertUnauthorized();
    });

    it('refuse un client et un prestataire', function () {
        $cible = User::factory()->create();
        foreach ([User::factory()->create(), User::factory()->prestataire()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->postJson(urlSanctions($cible), corpsSanction())->assertForbidden();
            $this->actingAs($user, 'sanctum')->getJson(urlSanctions($cible))->assertForbidden();
        }
        expect(Sanction::count())->toBe(0);
    });

    it('répond 404 pour un utilisateur ou une sanction inconnus', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');

        $this->postJson('/api/v1/admin/utilisateurs/'.fake()->uuid().'/sanctions', corpsSanction())
            ->assertNotFound()->assertJsonPath('message', 'Utilisateur introuvable.');
        $this->postJson('/api/v1/admin/utilisateurs/pas-un-uuid/sanctions', corpsSanction())->assertNotFound();
        $this->getJson('/api/v1/admin/utilisateurs/'.fake()->uuid().'/sanctions')->assertNotFound();
        $this->postJson('/api/v1/admin/sanctions/'.fake()->uuid().'/lever')
            ->assertNotFound()->assertJsonPath('message', 'Sanction introuvable.');
    });

    it('valide le corps avec des messages en français', function (array $corps, string $champ, string $message) {
        $cible = User::factory()->create();
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson(urlSanctions($cible), $corps)
            ->assertUnprocessable()->assertJsonPath("errors.$champ.0", $message);
        expect(Sanction::count())->toBe(0);
    })->with([
        'type absent' => [['motif' => 'Un motif suffisant.'], 'type', 'Le type de sanction est obligatoire.'],
        'type inconnu' => [['type' => 'exil', 'motif' => 'Un motif suffisant.'], 'type', 'Le type doit être : avertissement, suspension_temporaire, bannissement_definitif.'],
        'motif absent' => [['type' => 'avertissement'], 'motif', 'Le motif de la sanction est obligatoire.'],
        'motif trop court' => [['type' => 'avertissement', 'motif' => 'court'], 'motif', 'Le motif doit contenir au moins 10 caractères.'],
        'suspension sans date' => [['type' => 'suspension_temporaire', 'motif' => 'Un motif suffisant.'], 'dateFin', 'La date de fin de la suspension est obligatoire.'],
        'date passée' => [['type' => 'suspension_temporaire', 'motif' => 'Un motif suffisant.', 'dateFin' => '2000-01-01'], 'dateFin', 'La date de fin doit être dans le futur.'],
    ]);

    it('refuse de sanctionner un administrateur, y compris soi-même', function () {
        $admin = User::factory()->administrateur()->create();
        $autre = User::factory()->administrateur()->create();
        $this->actingAs($admin, 'sanctum');

        foreach ([$autre, $admin] as $cible) {
            $this->postJson(urlSanctions($cible), corpsSanction('bannissement_definitif'))
                ->assertForbidden()->assertJsonPath('message', 'Un administrateur ne peut pas être sanctionné.');
            expect($cible->fresh()->statut)->toBe(StatutCompte::Actif);
        }
        expect(Sanction::count())->toBe(0);
    });

    it('limite à 10 poses par minute', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $cible = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(urlSanctions($cible), corpsSanction('avertissement', ['motif' => "Avertissement numéro $i."]))->assertCreated();
        }
        $this->postJson(urlSanctions($cible), corpsSanction())->assertStatus(429);
    });
});

describe('avertissement', function () {
    it('enregistre l\'avertissement sans toucher au compte ni aux jetons', function () {
        Event::fake([SanctionPosee::class]);
        $admin = User::factory()->administrateur()->create();
        $cible = User::factory()->create();
        $cible->createToken('t');

        $this->actingAs($admin, 'sanctum')->postJson(urlSanctions($cible), corpsSanction())
            ->assertCreated()
            ->assertJsonPath('type', 'avertissement')
            ->assertJsonPath('active', true)
            ->assertJsonPath('posePar', $admin->id)
            ->assertJsonPath('utilisateurId', $cible->id);

        expect($cible->fresh()->statut)->toBe(StatutCompte::Actif)
            ->and($cible->tokens()->count())->toBe(1);
        Event::assertDispatchedTimes(SanctionPosee::class, 1);
    });

    it('refuse le même avertissement envoyé deux fois, mais accepte un motif différent', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $cible = User::factory()->create();

        $this->postJson(urlSanctions($cible), corpsSanction())->assertCreated();
        $this->postJson(urlSanctions($cible), corpsSanction())
            ->assertStatus(409)->assertJsonPath('message', 'Cet avertissement vient déjà d\'être enregistré.');
        $this->postJson(urlSanctions($cible), corpsSanction('avertissement', ['motif' => 'Un autre motif suffisant.']))->assertCreated();

        expect(Sanction::count())->toBe(2);
    });

    it('peut viser un client comme un prestataire', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');

        foreach ([User::factory()->create(), prestataireSanctionnable()->user] as $cible) {
            $this->postJson(urlSanctions($cible), corpsSanction())->assertCreated();
        }
    });
});

describe('suspension et bannissement', function () {
    it('suspend en une transaction : sanction, statut du compte et révocation de tous les jetons', function () {
        Event::fake([SanctionPosee::class]);
        $admin = User::factory()->administrateur()->create();
        $cible = User::factory()->create();
        $cible->createToken('mobile');
        $cible->createToken('web');
        $fin = now()->addDays(3)->startOfSecond();

        $this->actingAs($admin, 'sanctum')
            ->postJson(urlSanctions($cible), corpsSanction('suspension_temporaire', ['dateFin' => $fin->toIso8601String()]))
            ->assertCreated()
            ->assertJsonPath('type', 'suspension_temporaire')
            ->assertJsonPath('dateFin', $fin->toIso8601String());

        expect($cible->fresh()->statut)->toBe(StatutCompte::Suspendu)
            ->and($cible->tokens()->count())->toBe(0)
            ->and(Sanction::count())->toBe(1);
        Event::assertDispatchedTimes(SanctionPosee::class, 1);
    });

    it('bannit définitivement et ignore une dateFin éventuelle', function () {
        $cible = User::factory()->create();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson(urlSanctions($cible), corpsSanction('bannissement_definitif', ['dateFin' => now()->addDay()->toIso8601String()]))
            ->assertCreated()->assertJsonPath('dateFin', null);

        expect($cible->fresh()->statut)->toBe(StatutCompte::Banni);
    });

    it('refuse une deuxième sanction bloquante tant que la première est active', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $cible = User::factory()->create();

        $this->postJson(urlSanctions($cible), corpsSanction('bannissement_definitif'))->assertCreated();
        $this->postJson(urlSanctions($cible), corpsSanction('suspension_temporaire', ['dateFin' => now()->addDay()->toIso8601String()]))
            ->assertStatus(409)->assertJsonPath('message', 'Ce compte fait déjà l\'objet d\'une sanction active.');

        expect(Sanction::count())->toBe(1)->and($cible->fresh()->statut)->toBe(StatutCompte::Banni);
    });

    it('refuse de suspendre un compte qui n\'est pas actif', function () {
        $cible = User::factory()->statut(StatutCompte::EnAttenteValidation)->create();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson(urlSanctions($cible), corpsSanction('bannissement_definitif'))
            ->assertStatus(409)->assertJsonPath('message', 'Ce compte n\'est pas actif : il ne peut pas être suspendu ou banni.');

        expect(Sanction::count())->toBe(0);
    });

    it('simule deux poses simultanées : l\'index unique n\'en laisse passer qu\'une', function () {
        $cible = User::factory()->create();
        Sanction::factory()->suspension()->create(['user_id' => $cible->id]);
        // Le statut lu par la seconde requête était encore ACTIF : seul l'index unique l'arrête.
        expect(fn () => Sanction::factory()->bannissement()->create(['user_id' => $cible->id]))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('refuse un litige dont le compte n\'est pas partie, et un avis dont il n\'est pas l\'auteur', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $cible = User::factory()->create();
        $litige = Litige::factory()->create();

        $this->postJson(urlSanctions($cible), corpsSanction('avertissement', ['litigeId' => $litige->id]))
            ->assertUnprocessable()->assertJsonPath('message', 'Ce compte n\'est pas une partie de ce litige.');
        $this->postJson(urlSanctions($cible), corpsSanction('avertissement', ['litigeId' => fake()->uuid()]))
            ->assertNotFound()->assertJsonPath('message', 'Litige introuvable.');
        $this->postJson(urlSanctions($cible), corpsSanction('avertissement', ['avisId' => fake()->uuid()]))
            ->assertNotFound()->assertJsonPath('message', 'Avis introuvable.');
    });

    it('rattache la sanction à un litige dont le compte est partie', function () {
        $litige = Litige::factory()->create();
        $cible = $litige->prestataire->user;
        $cible->forceFill(['statut' => StatutCompte::Actif])->save();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson(urlSanctions($cible), corpsSanction('avertissement', ['litigeId' => $litige->id]))
            ->assertCreated()->assertJsonPath('litigeId', $litige->id);
    });
});

describe('effets sur le compte sanctionné', function () {
    it('refuse la connexion avec le motif et la date de fin de la suspension', function () {
        $this->travelTo(now()->startOfDay()->addHours(10));
        $cible = User::factory()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);
        $admin = User::factory()->administrateur()->create();
        $fin = now()->addDays(2)->setTimezone(config('app.timezone'));

        $this->actingAs($admin, 'sanctum')->postJson(urlSanctions($cible), corpsSanction('suspension_temporaire', ['dateFin' => $fin->toIso8601String()]))->assertCreated();
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Votre compte est suspendu jusqu\'au '.$fin->format('d/m/Y \à H:i').'. Motif : Comportement injurieux envers un client.');
    });

    it('refuse la connexion d\'un compte banni avec le motif', function () {
        $cible = User::factory()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);
        Sanction::factory()->bannissement()->create(['user_id' => $cible->id, 'motif' => 'Fraude avérée sur le paiement.']);
        $cible->forceFill(['statut' => StatutCompte::Banni])->save();

        $this->postJson('/api/v1/auth/connexion', ['telephone' => '771234567', 'password' => 'motdepasse1'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Votre compte a été banni. Motif : Fraude avérée sur le paiement.');
    });

    it('rend inutilisable un jeton émis avant la sanction', function () {
        $cible = User::factory()->create();
        $jeton = $cible->createToken('mobile')->plainTextToken;
        $this->withToken($jeton)->getJson('/api/v1/auth/moi')->assertOk();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson(urlSanctions($cible), corpsSanction('bannissement_definitif'))->assertCreated();
        app('auth')->forgetGuards();

        $this->withToken($jeton)->getJson('/api/v1/auth/moi')->assertUnauthorized();
    });

    it('retire le prestataire de la recherche et de son profil public', function () {
        $p = prestataireSanctionnable();
        $client = User::factory()->create();
        $this->actingAs($client, 'sanctum')->getJson('/api/v1/prestataires/'.$p->id)->assertOk();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson(urlSanctions($p->user), corpsSanction('suspension_temporaire', ['dateFin' => now()->addDay()->toIso8601String()]))->assertCreated();

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/prestataires/'.$p->id)->assertNotFound();
        $ids = collect($this->actingAs($client, 'sanctum')->getJson('/api/v1/prestataires')->assertOk()->json('data'))->pluck('id');
        expect($ids)->not->toContain($p->id);
    });

    it('annule les réservations à venir du prestataire, bloque les créneaux et rembourse l\'acompte en intégralité', function () {
        Event::fake([SanctionPosee::class]);
        $p = prestataireSanctionnable();
        $enAttente = reservationSanction($p, 'enAttente');
        $acceptee = reservationSanction($p, 'acceptee');
        $confirmee = reservationSanction($p, 'confirmee', payee: true);
        $enCours = reservationSanction($p, 'enCours', payee: true);
        $terminee = reservationSanction($p, 'terminee', payee: true);
        $soldeAvant = $p->solde;

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson(urlSanctions($p->user), corpsSanction('bannissement_definitif'))->assertCreated();

        foreach ([$enAttente, $acceptee, $confirmee] as $r) {
            expect($r->fresh()->statut)->toBe(StatutReservation::Annulee)
                ->and($r->creneau->fresh()->statut)->toBe(StatutCreneau::Indisponible);
        }
        expect($enCours->fresh()->statut)->toBe(StatutReservation::EnCours)
            ->and($terminee->fresh()->statut)->toBe(StatutReservation::Terminee)
            ->and($confirmee->paiements()->first()->statut)->toBe(StatutPaiement::Rembourse)
            ->and($enCours->paiements()->first()->statut)->toBe(StatutPaiement::Reussi)
            ->and($p->fresh()->solde)->toBe($soldeAvant);
    });

    it('annule seulement les réservations en attente et acceptées d\'un client sanctionné, et libère leurs créneaux', function () {
        $p = prestataireSanctionnable();
        $client = User::factory()->create();
        $enAttente = reservationSanction($p, 'enAttente', client: $client);
        $acceptee = reservationSanction($p, 'acceptee', client: $client);
        $confirmee = reservationSanction($p, 'confirmee', payee: true, client: $client);

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson(urlSanctions($client), corpsSanction('bannissement_definitif'))->assertCreated();

        foreach ([$enAttente, $acceptee] as $r) {
            expect($r->fresh()->statut)->toBe(StatutReservation::Annulee)
                ->and($r->creneau->fresh()->statut)->toBe(StatutCreneau::Libre);
        }
        expect($confirmee->fresh()->statut)->toBe(StatutReservation::Confirmee)
            ->and($confirmee->paiements()->first()->statut)->toBe(StatutPaiement::Reussi);
    });

    it('un avertissement ne touche ni aux réservations ni au profil public', function () {
        $p = prestataireSanctionnable();
        $r = reservationSanction($p, 'confirmee', payee: true);

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson(urlSanctions($p->user), corpsSanction())->assertCreated();

        expect($r->fresh()->statut)->toBe(StatutReservation::Confirmee);
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/prestataires/'.$p->id)->assertOk();
    });
});

describe('historique et levée', function () {
    it('liste l\'historique du compte, de la plus récente à la plus ancienne', function () {
        $cible = User::factory()->create();
        $ancienne = Sanction::factory()->create(['user_id' => $cible->id, 'created_at' => now()->subDays(5)]);
        $recente = Sanction::factory()->levee()->create(['user_id' => $cible->id, 'created_at' => now()->subDay()]);
        Sanction::factory()->create();

        $reponse = $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->getJson(urlSanctions($cible))->assertOk();

        expect(collect($reponse->json('data'))->pluck('id')->all())->toBe([$recente->id, $ancienne->id])
            ->and($reponse->json('data.0.active'))->toBeFalse();
    });

    it('lève une suspension : le compte redevient actif et l\'événement est publié', function () {
        Event::fake([SanctionLevee::class]);
        $admin = User::factory()->administrateur()->create();
        $cible = User::factory()->statut(StatutCompte::Suspendu)->create();
        $sanction = Sanction::factory()->suspension()->create(['user_id' => $cible->id]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/sanctions/'.$sanction->id.'/lever')
            ->assertOk()->assertJsonPath('active', false)->assertJsonPath('leveePar', $admin->id);

        expect($cible->fresh()->statut)->toBe(StatutCompte::Actif);
        Event::assertDispatchedTimes(SanctionLevee::class, 1);
    });

    it('lève un bannissement, et répond 409 à une seconde levée', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $cible = User::factory()->statut(StatutCompte::Banni)->create();
        $sanction = Sanction::factory()->bannissement()->create(['user_id' => $cible->id]);

        $this->postJson('/api/v1/admin/sanctions/'.$sanction->id.'/lever')->assertOk();
        $this->postJson('/api/v1/admin/sanctions/'.$sanction->id.'/lever')
            ->assertStatus(409)->assertJsonPath('message', 'Cette sanction est déjà levée.');

        expect($cible->fresh()->statut)->toBe(StatutCompte::Actif);
    });

    it('permet une nouvelle sanction après la levée', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $cible = User::factory()->create();

        $id = $this->postJson(urlSanctions($cible), corpsSanction('bannissement_definitif'))->assertCreated()->json('id');
        $this->postJson('/api/v1/admin/sanctions/'.$id.'/lever')->assertOk();
        $this->postJson(urlSanctions($cible), corpsSanction('bannissement_definitif'))->assertCreated();

        expect(Sanction::count())->toBe(2)->and($cible->fresh()->statut)->toBe(StatutCompte::Banni);
    });

    it('ne rend pas leurs réservations annulées ni leur argent', function () {
        $p = prestataireSanctionnable();
        $r = reservationSanction($p, 'confirmee', payee: true);
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');

        $id = $this->postJson(urlSanctions($p->user), corpsSanction('bannissement_definitif'))->assertCreated()->json('id');
        $this->postJson('/api/v1/admin/sanctions/'.$id.'/lever')->assertOk();

        expect($r->fresh()->statut)->toBe(StatutReservation::Annulee)
            ->and($r->paiements()->first()->statut)->toBe(StatutPaiement::Rembourse);
    });
});

describe('levée automatique des suspensions échues', function () {
    it('lève les suspensions échues, laisse les autres et rend l\'accès', function () {
        Event::fake([SanctionLevee::class]);
        $this->travelTo(now()->startOfDay()->addHours(10));
        $echue = User::factory()->statut(StatutCompte::Suspendu)->create();
        $enCours = User::factory()->statut(StatutCompte::Suspendu)->create();
        $banni = User::factory()->statut(StatutCompte::Banni)->create();
        $sEchue = Sanction::factory()->suspension(now()->addHour())->create(['user_id' => $echue->id]);
        $sEnCours = Sanction::factory()->suspension(now()->addDays(2))->create(['user_id' => $enCours->id]);
        $sBan = Sanction::factory()->bannissement()->create(['user_id' => $banni->id]);

        $this->artisan('sopi:sanctions:lever-expirees')->assertSuccessful();
        expect($echue->fresh()->statut)->toBe(StatutCompte::Suspendu);

        $this->travelTo(now()->addHours(2));
        $this->artisan('sopi:sanctions:lever-expirees')->expectsOutputToContain('1')->assertSuccessful();

        expect($echue->fresh()->statut)->toBe(StatutCompte::Actif)
            ->and($sEchue->fresh()->levee_le)->not->toBeNull()
            ->and($sEchue->fresh()->levee_par_id)->toBeNull()
            ->and($enCours->fresh()->statut)->toBe(StatutCompte::Suspendu)
            ->and($sEnCours->fresh()->levee_le)->toBeNull()
            ->and($banni->fresh()->statut)->toBe(StatutCompte::Banni)
            ->and($sBan->fresh()->levee_le)->toBeNull();
        Event::assertDispatchedTimes(SanctionLevee::class, 1);
    });

    it('est idempotente : un second passage ne lève rien', function () {
        Event::fake([SanctionLevee::class]);
        $cible = User::factory()->statut(StatutCompte::Suspendu)->create();
        Sanction::factory()->suspension(now()->addMinute())->create(['user_id' => $cible->id]);
        $this->travelTo(now()->addMinutes(2));

        $this->artisan('sopi:sanctions:lever-expirees')->assertSuccessful();
        $this->artisan('sopi:sanctions:lever-expirees')->assertSuccessful();

        Event::assertDispatchedTimes(SanctionLevee::class, 1);
    });

    it('ne réactive pas un compte suspendu levé à la main avant l\'échéance', function () {
        $cible = User::factory()->statut(StatutCompte::Suspendu)->create();
        Sanction::factory()->suspension(now()->addMinute())->levee()->create(['user_id' => $cible->id]);
        $this->travelTo(now()->addMinutes(2));

        $this->artisan('sopi:sanctions:lever-expirees')->assertSuccessful();

        expect($cible->fresh()->statut)->toBe(StatutCompte::Suspendu);
    });

    it('est planifiée toutes les 5 minutes sans chevauchement', function () {
        $evenement = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'sopi:sanctions:lever-expirees'));

        expect($evenement)->not->toBeNull()
            ->and($evenement->expression)->toBe('*/5 * * * *')
            ->and($evenement->withoutOverlapping)->toBeTrue();
    });
});

describe('décision d\'un litige avec sanction', function () {
    function litigeATrancher(): Litige
    {
        $litige = Litige::factory()->create(['statut' => StatutLitige::Ouvert]);
        $litige->prestataire->user->forceFill(['statut' => StatutCompte::Actif])->save();

        return $litige;
    }

    it('clôt le litige et sanctionne le prestataire dans la même transaction', function () {
        $litige = litigeATrancher();
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', [
                'decision' => 'bannissement_definitif', 'motif' => 'Absence répétée aux rendez-vous.',
            ])->assertOk()->assertJsonPath('statut', 'cloture')->assertJsonPath('decision', 'bannissement_definitif');

        $sanction = Sanction::first();
        expect($sanction->litige_id)->toBe($litige->id)
            ->and($sanction->user_id)->toBe($litige->prestataire->user_id)
            ->and($litige->prestataire->user->fresh()->statut)->toBe(StatutCompte::Banni);
    });

    it('peut viser le client', function () {
        $litige = litigeATrancher();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', [
                'decision' => 'avertissement', 'motif' => 'Description mensongère du litige.', 'cible' => 'client',
            ])->assertOk();

        expect(Sanction::first()->user_id)->toBe($litige->client_id);
    });

    it('exige une date de fin pour une suspension et la pose avec elle', function () {
        $litige = litigeATrancher();
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $corps = ['decision' => 'suspension_temporaire', 'motif' => 'Retards répétés constatés.'];

        $this->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', $corps)
            ->assertUnprocessable()->assertJsonPath('errors.dateFin.0', 'La date de fin de la suspension est obligatoire.');
        expect($litige->fresh()->statut)->toBe(StatutLitige::Ouvert);

        $this->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', $corps + ['dateFin' => now()->addDays(5)->toIso8601String()])->assertOk();
        expect($litige->prestataire->user->fresh()->statut)->toBe(StatutCompte::Suspendu);
    });

    it('annule la clôture si la sanction échoue (compte déjà sanctionné)', function () {
        $litige = litigeATrancher();
        $litige->prestataire->user->forceFill(['statut' => StatutCompte::Banni])->save();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', [
                'decision' => 'bannissement_definitif', 'motif' => 'Absence répétée aux rendez-vous.',
            ])->assertStatus(409);

        expect($litige->fresh()->statut)->toBe(StatutLitige::Ouvert)
            ->and($litige->fresh()->decision)->toBeNull()
            ->and(Sanction::count())->toBe(0);
    });

    it('un rejet ne pose aucune sanction', function () {
        $litige = litigeATrancher();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', ['decision' => 'rejet', 'motif' => 'Signalement non fondé.'])->assertOk();

        expect(Sanction::count())->toBe(0)->and($litige->fresh()->decision)->toBe(DecisionLitige::Rejet);
    });

    it('refuse une seconde décision sur un litige clôturé, sans seconde sanction', function () {
        $litige = litigeATrancher();
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $corps = ['decision' => 'avertissement', 'motif' => 'Absence répétée aux rendez-vous.'];

        $this->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', $corps)->assertOk();
        $this->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', $corps)
            ->assertStatus(409)->assertJsonPath('message', 'Ce litige est déjà clôturé.');

        expect(Sanction::count())->toBe(1);
    });
});
