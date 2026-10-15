<?php

use App\Enums\CauseAnnulation;
use App\Enums\CauseExpiration;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Enums\StatutEnvoi;
use App\Enums\TypeNotification;
use App\Events\AvisDepose;
use App\Events\AvisModere;
use App\Events\LitigeCloture;
use App\Events\LitigeOuvert;
use App\Events\Reservation\ReservationAcceptee;
use App\Events\Reservation\ReservationAnnulee;
use App\Events\Reservation\ReservationConfirmee;
use App\Events\Reservation\ReservationCreee;
use App\Events\Reservation\ReservationDemarree;
use App\Events\Reservation\ReservationExpiree;
use App\Events\Reservation\ReservationRefusee;
use App\Events\Reservation\ReservationTerminee;
use App\Events\SanctionLevee;
use App\Events\SanctionPosee;
use App\Models\Avis;
use App\Models\Creneau;
use App\Models\Litige;
use App\Models\Notification;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\Sanction;
use App\Models\User;
use App\Services\Notification\EnvoiEmail;
use App\Services\Notification\NotificationService;
use App\Services\Paiement\PaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

const SECRET_NOTIFS = 'secret-factice-notifications';

beforeEach(function () {
    Http::preventStrayRequests();
    config(['sopi.paiement.driver' => 'fake', 'sopi.paiement.webhook_secret' => SECRET_NOTIFS]);
    app()->forgetInstance(PaiementService::class);
    foreach (['reservation', 'sanction', 'litige', 'avis', 'reponse-reservation', 'code-reservation', 'paiement-reservation', 'verrou-creneau'] as $limiteur) {
        RateLimiter::clear($limiteur);
    }
});

/** Client (Awa Diop), prestataire (Garage Sow), prestation « Vidange » à 15 000 FCFA, créneau dans 2 jours. Tous deux ont un jeton push. */
function decorNotifs(string $etat = 'enAttente'): array
{
    $client = User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diop', 'jeton_push' => 'jeton-client']);
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire()->state(['jeton_push' => 'jeton-prestataire']), 'user')->create(['nom_entreprise' => 'Garage Sow']);
    $prestation = Prestation::factory()->for($prestataire)->create(['nom' => 'Vidange', 'prix' => '15000.00']);
    $debut = now()->addDays(2)->startOfHour();
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
    $reservation = Reservation::factory()->{$etat}()->create(['client_id' => $client->id, 'creneau_id' => $creneau->id, 'prestation_id' => $prestation->id]);

    return [$client, $prestataire, $reservation];
}

function notifsDe(User $user)
{
    return Notification::query()->where('user_id', $user->id)->orderBy('created_at')->get();
}

/** Aucune donnée sensible dans ce qui part par push et par e-mail : ni code de réservation, ni montant, ni téléphone, ni jeton. */
function verifierSansDonneeSensible(Notification $n, ?string $code = null): void
{
    $texte = $n->titre.' '.$n->corps.' '.json_encode($n->donnees);
    foreach (['15000', '15 000', '4500', '4 500', 'FCFA', '+221', 'jeton-', 'Diop'] as $interdit) {
        expect($texte)->not->toContain($interdit);
    }
    if ($code !== null) {
        expect($texte)->not->toContain($code);
    }
}

function unSeul(User $user, TypeNotification $type): Notification
{
    $liste = notifsDe($user)->where('type', $type);
    expect($liste)->toHaveCount(1);

    return $liste->first();
}

describe('réservation', function () {
    it('nouvelle demande : le prestataire est prévenu, avec le prénom et l\'initiale du client seulement', function () {
        [$client, $prestataire, $reservation] = decorNotifs();

        ReservationCreee::dispatch($reservation);

        $n = unSeul($prestataire->user, TypeNotification::ReservationNouvelleDemande);
        expect($n->corps)->toContain('Awa D.')->toContain('Vidange')->toContain('12 heures')
            ->and($n->donnees)->toBe(['reservationId' => $reservation->id])
            ->and($n->canal_email_statut)->toBe(StatutEnvoi::EnAttente)
            ->and($n->canal_push_statut)->toBe(StatutEnvoi::EnAttente)
            ->and(notifsDe($client))->toHaveCount(0);
        verifierSansDonneeSensible($n);
    });

    it('demande acceptée : le client est prévenu de payer l\'acompte dans le délai', function () {
        [$client, $prestataire, $reservation] = decorNotifs('acceptee');

        ReservationAcceptee::dispatch($reservation);

        $n = unSeul($client, TypeNotification::ReservationAcceptee);
        expect($n->corps)->toContain('Garage Sow')->toContain('Vidange')->toContain('2 heures')
            ->and(notifsDe($prestataire->user))->toHaveCount(0);
        verifierSansDonneeSensible($n);
    });

    it('demande refusée : le client est prévenu, sans le motif', function () {
        [$client, $prestataire, $reservation] = decorNotifs();
        DB::table('reservations')->where('id', $reservation->id)->update(['motif_refus' => 'Motif très privé du prestataire']);

        ReservationRefusee::dispatch($reservation->fresh());

        $n = unSeul($client, TypeNotification::ReservationRefusee);
        expect($n->corps)->toContain('Garage Sow')->not->toContain('privé')
            ->and(notifsDe($prestataire->user))->toHaveCount(0);
    });

    it('expiration : le client est prévenu selon la cause', function (CauseExpiration $cause, string $extrait) {
        [$client, $prestataire, $reservation] = decorNotifs();

        ReservationExpiree::dispatch($reservation, $cause);

        $n = unSeul($client, TypeNotification::ReservationExpiree);
        expect($n->corps)->toContain($extrait)->and(notifsDe($prestataire->user))->toHaveCount(0);
        verifierSansDonneeSensible($n);
    })->with([
        'sans réponse' => [CauseExpiration::SansReponse, 'n\'a pas répondu'],
        'impayée' => [CauseExpiration::Impayee, 'acompte n\'a pas été payé'],
    ]);

    it('annulation par le client : le prestataire est prévenu', function () {
        [$client, $prestataire, $reservation] = decorNotifs('confirmee');

        ReservationAnnulee::dispatch($reservation, Role::Client, CauseAnnulation::Demande);

        $n = unSeul($prestataire->user, TypeNotification::ReservationAnnulee);
        expect($n->corps)->toContain('Awa D.')->and(notifsDe($client))->toHaveCount(0);
        verifierSansDonneeSensible($n);
    });

    it('annulation par le prestataire ou indisponibilité : le client est prévenu du remboursement', function (CauseAnnulation $cause) {
        [$client, $prestataire, $reservation] = decorNotifs('confirmee');

        ReservationAnnulee::dispatch($reservation, Role::Prestataire, $cause);

        $n = unSeul($client, TypeNotification::ReservationAnnulee);
        expect($n->corps)->toContain('Garage Sow')->toContain('remboursé')->and(notifsDe($prestataire->user))->toHaveCount(0);
        verifierSansDonneeSensible($n);
    })->with([CauseAnnulation::Demande, CauseAnnulation::Indisponibilite]);

    it('annulation par sanction : la partie non sanctionnée est prévenue', function () {
        [$client, $prestataire, $reservation] = decorNotifs('confirmee');

        // Prestataire banni : le client est prévenu.
        $prestataire->user->forceFill(['statut' => StatutCompte::Banni])->save();
        ReservationAnnulee::dispatch($reservation, Role::Administrateur, CauseAnnulation::Sanction);
        expect(notifsDe($client))->toHaveCount(1)->and(notifsDe($prestataire->user))->toHaveCount(0);

        // Client sanctionné : c'est le prestataire qui est prévenu.
        $prestataire->user->forceFill(['statut' => StatutCompte::Actif])->save();
        ReservationAnnulee::dispatch($reservation->fresh(), Role::Administrateur, CauseAnnulation::Sanction);
        expect(notifsDe($prestataire->user))->toHaveCount(1)->and(notifsDe($client))->toHaveCount(1);
    });

    it('réservation confirmée : le prestataire est prévenu, sans le code ni le montant', function () {
        [$client, $prestataire, $reservation] = decorNotifs('confirmee');
        $code = $reservation->code_reservation;

        ReservationConfirmee::dispatch($reservation);

        $n = unSeul($prestataire->user, TypeNotification::ReservationConfirmee);
        expect($n->corps)->toContain('acompte est payé')->toContain('Awa D.')->and(notifsDe($client))->toHaveCount(0);
        verifierSansDonneeSensible($n, $code);
    });

    it('prestation démarrée puis terminée : le client est prévenu', function () {
        [$client, $prestataire, $reservation] = decorNotifs('enCours');

        ReservationDemarree::dispatch($reservation);
        ReservationTerminee::dispatch($reservation);

        expect(unSeul($client, TypeNotification::ReservationDemarree)->corps)->toContain('Garage Sow')
            ->and(unSeul($client, TypeNotification::ReservationTerminee)->corps)->toContain('laisser un avis')
            ->and(notifsDe($prestataire->user))->toHaveCount(0);
        notifsDe($client)->each(fn ($n) => verifierSansDonneeSensible($n, $reservation->code_reservation));
    });
});

describe('avis', function () {
    it('avis déposé : le prestataire est prévenu, avec la note', function () {
        [$client, $prestataire, $reservation] = decorNotifs('terminee');
        $avis = Avis::factory()->note(2)->create(['reservation_id' => $reservation->id]);

        AvisDepose::dispatch($avis);

        $n = unSeul($prestataire->user, TypeNotification::AvisRecu);
        expect($n->corps)->toContain('Awa D.')->toContain('2/5')->toContain('Vidange')->and(notifsDe($client))->toHaveCount(0);
    });

    it('avis modéré : le prestataire est prévenu, que l\'avis soit masqué ou conservé', function () {
        [, $prestataire, $reservation] = decorNotifs('terminee');
        $avis = Avis::factory()->create(['reservation_id' => $reservation->id]);

        AvisModere::dispatch($avis, true);
        AvisModere::dispatch($avis, false);

        $corps = notifsDe($prestataire->user)->where('type', TypeNotification::AvisModere)->pluck('corps')->all();
        expect($corps)->toHaveCount(2)->and($corps[0])->toContain('supprimé')->and($corps[1])->toContain('conservé');
    });
});

describe('litiges et sanctions', function () {
    it('litige ouvert : le prestataire concerné est prévenu', function () {
        [$client, $prestataire, $reservation] = decorNotifs('confirmee');
        $litige = Litige::factory()->create(['reservation_id' => $reservation->id]);

        LitigeOuvert::dispatch($litige);

        $n = unSeul($prestataire->user, TypeNotification::LitigeOuvert);
        expect($n->corps)->toContain('Vidange')->not->toContain('absent')->and(notifsDe($client))->toHaveCount(0);
        verifierSansDonneeSensible($n);
    });

    it('litige rejeté : le client et le prestataire sont prévenus', function () {
        [$client, $prestataire, $reservation] = decorNotifs('confirmee');
        $litige = Litige::factory()->cloture()->create(['reservation_id' => $reservation->id]);

        LitigeCloture::dispatch($litige);

        expect(unSeul($client, TypeNotification::LitigeCloture)->corps)->toContain('pas été retenu')
            ->and(unSeul($prestataire->user, TypeNotification::LitigeCloture)->corps)->toContain('pas été retenu');
    });

    it('litige clôturé par une sanction : la personne sanctionnée reçoit la sanction, l\'autre partie la clôture', function () {
        [$client, $prestataire, $reservation] = decorNotifs('confirmee');
        $admin = User::factory()->administrateur()->create();
        $litige = Litige::factory()->create(['reservation_id' => $reservation->id]);

        test()->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', [
            'decision' => 'avertissement', 'motif' => 'Le prestataire était absent.', 'cible' => 'prestataire',
        ])->assertOk();

        expect(unSeul($client, TypeNotification::LitigeCloture)->corps)->toContain('décision')
            ->and(notifsDe($client))->toHaveCount(1)
            ->and(unSeul($prestataire->user, TypeNotification::SanctionPosee)->corps)->toContain('avertissement')
            ->and(notifsDe($prestataire->user))->toHaveCount(1)
            ->and(Notification::query()->where('user_id', $admin->id)->count())->toBe(0);
    });

    it('sanction posée : la personne sanctionnée est prévenue selon le type, même si son compte est bloqué', function () {
        $prestataire = Prestataire::factory()->for(User::factory()->prestataire()->state(['jeton_push' => 'jeton-p']), 'user')->create();
        $admin = User::factory()->administrateur()->create();
        $cible = $prestataire->user;

        test()->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/utilisateurs/'.$cible->id.'/sanctions', [
            'type' => 'suspension_temporaire', 'motif' => 'Comportement injurieux envers un client.', 'dateFin' => now()->addDays(3)->toIso8601String(),
        ])->assertCreated();

        $n = unSeul($cible, TypeNotification::SanctionPosee);
        expect($n->corps)->toContain('suspendu jusqu\'au '.now()->addDays(3)->format('d/m/Y'))->not->toContain('injurieux')
            ->and($n->canal_email_statut)->not->toBe(StatutEnvoi::NonApplicable)
            ->and(Notification::query()->where('user_id', $admin->id)->count())->toBe(0);
    });

    it('bannissement : message dédié', function () {
        $sanction = Sanction::factory()->bannissement()->create();

        SanctionPosee::dispatch($sanction);

        expect(unSeul($sanction->user, TypeNotification::SanctionPosee)->corps)->toContain('banni définitivement');
    });

    it('sanction levée : la personne est prévenue, par l\'administrateur ou automatiquement', function () {
        $sanction = Sanction::factory()->suspension()->levee()->create();

        SanctionLevee::dispatch($sanction);

        expect(unSeul($sanction->user, TypeNotification::SanctionLevee)->corps)->toContain('levée');
    });
});

describe('dossier prestataire et modération par les routes', function () {
    it('dossier validé ou refusé : le prestataire est prévenu par e-mail', function () {
        $admin = User::factory()->administrateur()->create();
        $a = Prestataire::factory()->for(User::factory()->prestataire()->statut(StatutCompte::EnAttenteValidation), 'user')->create();
        $b = Prestataire::factory()->for(User::factory()->prestataire()->statut(StatutCompte::EnAttenteValidation), 'user')->create();

        test()->actingAs($admin, 'sanctum');
        test()->postJson('/api/v1/admin/prestataires/'.$a->id.'/accepter')->assertOk();
        test()->postJson('/api/v1/admin/prestataires/'.$b->id.'/refuser', ['motif' => 'La photocopie de la CNI est illisible.'])->assertOk();

        $oui = unSeul($a->user, TypeNotification::DemandeAcceptee);
        $non = unSeul($b->user, TypeNotification::DemandeRefusee);
        expect($oui->corps)->toContain('validé')
            ->and($oui->canal_email_statut)->not->toBe(StatutEnvoi::NonApplicable)
            ->and($non->canal_email_statut)->not->toBe(StatutEnvoi::NonApplicable)
            ->and($non->corps)->not->toContain('CNI');
    });

    it('modération d\'un avis par l\'administrateur : le prestataire est prévenu', function () {
        [, $prestataire, $reservation] = decorNotifs('terminee');
        $avis = Avis::factory()->create(['reservation_id' => $reservation->id]);

        test()->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson('/api/v1/admin/avis/'.$avis->id.'/masquer', ['motif' => 'Propos injurieux dans le commentaire.'])->assertOk();

        expect(unSeul($prestataire->user, TypeNotification::AvisModere)->corps)->toContain('supprimé');
    });
});

describe('garanties', function () {
    it('ne crée aucune notification quand la transaction est annulée', function () {
        [, , $reservation] = decorNotifs('acceptee');

        try {
            DB::transaction(function () use ($reservation) {
                ReservationAcceptee::dispatch($reservation);
                throw new RuntimeException('annulation');
            });
        } catch (RuntimeException) {
        }

        expect(Notification::count())->toBe(0);
    });

    it('ne notifie jamais un administrateur, même s\'il est partie d\'un événement', function () {
        $admin = User::factory()->administrateur()->create(['jeton_push' => 'jeton-admin']);
        $sanction = Sanction::factory()->create(['user_id' => $admin->id]);

        SanctionPosee::dispatch($sanction);

        expect(Notification::count())->toBe(0);
    });

    it('un échec de la création de notification n\'empêche pas l\'action métier', function () {
        app()->instance(NotificationService::class, new class implements NotificationService
        {
            public function creer(User $utilisateur, TypeNotification $type, string $titre, string $corps, array $donnees = [], array $canaux = []): ?Notification
            {
                throw new RuntimeException('base indisponible adresse@example.test');
            }

            public function envoyer(Notification $notification): void {}

            public function envoyerEnAttente(int $limite): int
            {
                return 0;
            }
        });
        [, $prestataire, $reservation] = decorNotifs();
        $messages = [];
        Log::listen(function ($log) use (&$messages) {
            $messages[] = $log->message.json_encode($log->context);
        });

        test()->actingAs($prestataire->user, 'sanctum')
            ->postJson('/api/v1/prestataire/reservations/'.$reservation->id.'/accepter')
            ->assertOk()->assertJsonPath('statut', 'acceptee');

        expect(implode('', $messages))->toContain(RuntimeException::class)->not->toContain('adresse@example.test');
    });

    it('une panne des pilotes d\'envoi n\'empêche aucune action, et la notification reste consultable', function () {
        config(['sopi.notifications.email_driver' => 'smtp', 'sopi.notifications.push_driver' => 'fcm']);
        app()->forgetInstance(EnvoiEmail::class);
        Mail::shouldReceive('raw')->andThrow(new RuntimeException('smtp hors service'));
        test()->withoutDefer();
        [$client, $prestataire, $reservation] = decorNotifs();

        test()->actingAs($prestataire->user, 'sanctum')
            ->postJson('/api/v1/prestataire/reservations/'.$reservation->id.'/accepter')->assertOk();

        $n = unSeul($client, TypeNotification::ReservationAcceptee);
        expect($n->canal_email_statut)->toBe(StatutEnvoi::EnAttente)
            ->and($n->tentatives)->toBe(1)
            ->and($n->dernier_echec_classe)->not->toBeNull();
    });
});

describe('bout en bout par les vraies routes', function () {
    it('réservation, acceptation, paiement, webhook, démarrage, fin : chaque étape notifie la bonne personne', function () {
        $client = User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diop', 'jeton_push' => 'jeton-client']);
        $prestataire = Prestataire::factory()->for(User::factory()->prestataire()->state(['jeton_push' => 'jeton-prestataire']), 'user')->create(['nom_entreprise' => 'Garage Sow']);
        $prestation = Prestation::factory()->for($prestataire)->create(['nom' => 'Vidange', 'prix' => '15000.00']);
        $debut = now()->addDays(2)->startOfHour();
        $creneau = Creneau::factory()->for($prestataire)->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
        $admin = User::factory()->administrateur()->create(['jeton_push' => 'jeton-admin']);

        test()->actingAs($client, 'sanctum');
        test()->postJson('/api/v1/creneaux/'.$creneau->id.'/verrou')->assertSuccessful();
        $id = test()->postJson('/api/v1/reservations', ['creneau_id' => $creneau->id, 'prestation_id' => $prestation->id])->assertCreated()->json('id');
        expect(notifsDe($prestataire->user)->pluck('type'))->toEqual(collect([TypeNotification::ReservationNouvelleDemande]));

        test()->actingAs($prestataire->user, 'sanctum')->postJson("/api/v1/prestataire/reservations/$id/accepter")->assertOk();
        expect(notifsDe($client)->pluck('type'))->toEqual(collect([TypeNotification::ReservationAcceptee]));

        test()->actingAs($client, 'sanctum')->postJson("/api/v1/reservations/$id/payer")->assertSuccessful();
        $paiement = Paiement::query()->where('reservation_id', $id)->firstOrFail();
        $corps = json_encode(['reference' => $paiement->reference_dexpay, 'statut' => 'reussi', 'montant' => $paiement->montant_acompte]);
        test()->call('POST', '/api/v1/webhooks/paiement', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $corps, SECRET_NOTIFS),
        ], $corps)->assertOk();
        $code = Reservation::findOrFail($id)->code_reservation;
        expect(notifsDe($prestataire->user)->pluck('type')->last())->toBe(TypeNotification::ReservationConfirmee);

        test()->actingAs($prestataire->user, 'sanctum')->postJson("/api/v1/prestataire/reservations/$id/demarrer", ['code' => $code])->assertOk();
        test()->postJson("/api/v1/prestataire/reservations/$id/terminer")->assertOk();

        expect(notifsDe($client)->pluck('type')->all())->toBe([TypeNotification::ReservationAcceptee, TypeNotification::ReservationDemarree, TypeNotification::ReservationTerminee])
            ->and(notifsDe($prestataire->user)->pluck('type')->all())->toBe([TypeNotification::ReservationNouvelleDemande, TypeNotification::ReservationConfirmee])
            ->and(Notification::query()->where('user_id', $admin->id)->count())->toBe(0);
        Notification::all()->each(fn (Notification $n) => verifierSansDonneeSensible($n, $code));
    });
});
