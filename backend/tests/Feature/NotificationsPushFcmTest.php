<?php

use App\Enums\PlateformeAppareil;
use App\Enums\StatutCompte;
use App\Enums\StatutEnvoi;
use App\Enums\TypeNotification;
use App\Exceptions\ConfigurationNotificationInvalide;
use App\Models\Appareil;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\EnvoiPush;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

require_once __DIR__.'/../Helpers/notifications_reelles.php';

function utilisateurAvecAppareils(array $jetons, array $attributs = []): User
{
    $user = User::factory()->create($attributs);
    foreach ($jetons as $jeton) {
        $a = new Appareil;
        $a->user_id = $user->id;
        $a->jeton = $jeton;
        $a->plateforme = PlateformeAppareil::Android;
        $a->save();
    }

    return $user;
}

function notificationPush(User $user, string $corps = 'Votre réservation est confirmée.'): Notification
{
    $n = new Notification;
    $n->user_id = $user->id;
    $n->type = TypeNotification::ReservationConfirmee;
    $n->titre = 'Réservation confirmée';
    $n->corps = $corps;
    $n->canal_email_statut = StatutEnvoi::NonApplicable;
    $n->canal_push_statut = StatutEnvoi::EnAttente;
    $n->save();

    return $n;
}

describe('configuration', function () {
    it('refuse sans FCM_CREDENTIALS en nommant la variable', function () {
        configurerFcm('');

        expect(fn () => app(EnvoiPush::class))->toThrow(ConfigurationNotificationInvalide::class, 'FCM_CREDENTIALS');
    });

    it('refuse un contenu illisible sans le recopier', function () {
        configurerFcm('contenu-illisible-secret');

        try {
            app(EnvoiPush::class);
        } catch (ConfigurationNotificationInvalide $e) {
            expect($e->getMessage())->toContain('illisible')->not->toContain('contenu-illisible-secret');

            return;
        }
        $this->fail('Une exception était attendue.');
    });

    it('nomme les champs absents du JSON', function () {
        configurerFcm(json_encode(['project_id' => 'p']));

        expect(fn () => app(EnvoiPush::class))->toThrow(ConfigurationNotificationInvalide::class, 'client_email, private_key');
    });

    it('exige FCM_PROJECT_ID quand le JSON ne le contient pas', function () {
        configurerFcm(json_encode(['client_email' => FCM_EMAIL_COMPTE, 'private_key' => cleFactice()]));

        expect(fn () => app(EnvoiPush::class))->toThrow(ConfigurationNotificationInvalide::class, 'FCM_PROJECT_ID');
    });

    it('accepte le JSON encodé en base64 et FCM_PROJECT_ID prioritaire', function () {
        $json = json_encode(['project_id' => 'projet-json', 'client_email' => FCM_EMAIL_COMPTE, 'private_key' => cleFactice(), 'token_uri' => 'https://oauth2.test/token']);
        configurerFcm(base64_encode($json), ['sopi.notifications.fcm.project_id' => 'projet-env']);
        fauxFcm([Http::response(['name' => 'ok'])]);
        $user = utilisateurAvecAppareils(['jeton-appareil-000000000001']);

        app(NotificationService::class)->envoyer(notificationPush($user));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/projects/projet-env/messages:send'));
    });

    it('laisse la notification en attente quand la configuration est mauvaise, sans planter', function () {
        configurerFcm('');
        $user = utilisateurAvecAppareils(['jeton-appareil-000000000001']);
        $n = notificationPush($user);

        app(NotificationService::class)->envoyer($n);

        $n->refresh();
        expect($n->canal_push_statut)->toBe(StatutEnvoi::EnAttente)->and($n->tentatives)->toBe(1)
            ->and($n->dernier_echec_message)->toContain('FCM_CREDENTIALS');
    });
});

describe('envoi', function () {
    it('envoie un push par appareil, court, sans code de réservation ni téléphone', function () {
        configurerFcm();
        fauxFcm([Http::response(['name' => 'm1']), Http::response(['name' => 'm2'])]);
        $user = utilisateurAvecAppareils(['jeton-appareil-000000000001', 'jeton-appareil-000000000002']);
        $n = notificationPush($user, str_repeat('Texte long. ', 40));

        app(NotificationService::class)->envoyer($n);

        $envois = Http::recorded(fn ($requete) => str_contains($requete->url(), 'messages:send'));
        expect($envois)->toHaveCount(2)->and($n->refresh()->canal_push_statut)->toBe(StatutEnvoi::Envoye);
        foreach ($envois as [$requete]) {
            $message = $requete->data()['message'];
            expect($requete->hasHeader('Authorization', 'Bearer jeton-acces-secret'))->toBeTrue()
                ->and(mb_strlen($message['notification']['body']))->toBeLessThanOrEqual(123)
                ->and($message['notification']['title'])->toBe('Réservation confirmée')
                ->and(array_keys($message['data']))->toBe(['type', 'notificationId']);
        }
        // Une seule authentification pour les deux messages (jeton d'accès mis en cache).
        expect(Http::recorded(fn ($r) => str_contains($r->url(), 'oauth2.test')))->toHaveCount(1);
    });

    it('transmet type et identifiants de l\'objet en chaînes, sans pouvoir écraser le type', function (TypeNotification $type, array $donnees, array $attendu) {
        configurerFcm();
        fauxFcm([Http::response(['name' => 'm1'])]);
        $user = utilisateurAvecAppareils(['jeton-appareil-000000000001']);
        $n = notificationPush($user);
        $n->type = $type;
        $n->donnees = $donnees;
        $n->save();

        app(NotificationService::class)->envoyer($n);

        $envois = Http::recorded(fn ($requete) => str_contains($requete->url(), 'messages:send'));
        $data = $envois->first()[0]->data()['message']['data'];
        expect($data)->toBe([...$attendu, 'type' => $type->value, 'notificationId' => (string) $n->id]);
        foreach ($data as $valeur) {
            expect($valeur)->toBeString();
        }
    })->with([
        'réservation demandée' => [TypeNotification::ReservationNouvelleDemande, ['reservationId' => 'r-1'], ['reservationId' => 'r-1']],
        'réservation acceptée' => [TypeNotification::ReservationAcceptee, ['reservationId' => 'r-2'], ['reservationId' => 'r-2']],
        'réservation confirmée' => [TypeNotification::ReservationConfirmee, ['reservationId' => 'r-3'], ['reservationId' => 'r-3']],
        'prestation terminée' => [TypeNotification::ReservationTerminee, ['reservationId' => 'r-4'], ['reservationId' => 'r-4']],
        'avis reçu' => [TypeNotification::AvisRecu, ['avisId' => 'a-1', 'reservationId' => 'r-5'], ['avisId' => 'a-1', 'reservationId' => 'r-5']],
        'litige ouvert' => [TypeNotification::LitigeOuvert, ['litigeId' => 'l-1', 'reservationId' => 'r-6'], ['litigeId' => 'l-1', 'reservationId' => 'r-6']],
        'valeurs non texte et type usurpé' => [TypeNotification::SanctionPosee, ['sanctionId' => 42, 'urgent' => true, 'liste' => ['x'], 'type' => 'pirate'], ['sanctionId' => '42', 'urgent' => '1']],
    ]);

    it('une erreur de FCM ne fait jamais échouer l\'envoi côté métier : la notification reste en attente', function () {
        configurerFcm();
        fauxFcm([Http::response(['error' => ['status' => 'INTERNAL']], 500)]);
        $user = utilisateurAvecAppareils(['jeton-appareil-000000000001']);
        $n = notificationPush($user);

        app(NotificationService::class)->envoyer($n);

        expect($n->refresh()->canal_push_statut)->toBe(StatutEnvoi::EnAttente)
            ->and(Appareil::query()->count())->toBe(1);
    });

    it('supprime un jeton refusé comme invalide et garde les autres', function (int $statut, array $corps) {
        configurerFcm();
        fauxFcm([Http::response($corps, $statut), Http::response(['name' => 'ok'])]);
        $user = utilisateurAvecAppareils(['jeton-invalide-0000000001', 'jeton-valide-000000000002']);
        $n = notificationPush($user);

        app(NotificationService::class)->envoyer($n);

        expect(Appareil::query()->pluck('jeton')->all())->toBe(['jeton-valide-000000000002'])
            ->and($n->refresh()->canal_push_statut)->toBe(StatutEnvoi::Envoye);
    })->with([
        'UNREGISTERED' => [404, ['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]]],
        'NOT_FOUND' => [404, ['error' => ['status' => 'NOT_FOUND']]],
        'INVALID_ARGUMENT jeton' => [400, ['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'The registration token is not a valid FCM registration token']]],
    ]);

    it('abandonne le canal push sans erreur quand tous les jetons sont invalides', function () {
        configurerFcm();
        fauxFcm([Http::response(['error' => ['status' => 'NOT_FOUND']], 404)]);
        $user = utilisateurAvecAppareils(['jeton-invalide-0000000001']);
        $n = notificationPush($user);

        app(NotificationService::class)->envoyer($n);

        expect(Appareil::query()->count())->toBe(0)->and($n->refresh()->dernier_echec_message)->toBeNull();
    });

    it('masque tout secret quand Google refuse l\'authentification et laisse la notification en attente', function () {
        configurerFcm();
        Http::fake(['oauth2.test/*' => Http::response(['error' => 'invalid_grant', 'error_description' => cleFactice().' '.FCM_EMAIL_COMPTE], 400)]);
        $user = utilisateurAvecAppareils(['jeton-appareil-000000000001']);
        $n = notificationPush($user);

        app(NotificationService::class)->envoyer($n);

        $n->refresh();
        expect($n->canal_push_statut)->toBe(StatutEnvoi::EnAttente)
            ->and($n->dernier_echec_message)->toContain('HTTP 400')->toContain('invalid_grant')
            ->not->toContain('PRIVATE KEY')->not->toContain(FCM_EMAIL_COMPTE)->not->toContain('jeton-appareil');
        expect(Appareil::query()->count())->toBe(1);
    });

    it('relance après une réponse 401 de FCM avec un nouveau jeton d\'accès', function () {
        configurerFcm();
        fauxFcm([Http::response(['error' => ['status' => 'UNAUTHENTICATED']], 401), Http::response(['name' => 'ok'])]);
        $user = utilisateurAvecAppareils(['jeton-appareil-000000000001']);
        $n = notificationPush($user);

        app(NotificationService::class)->envoyer($n);

        expect($n->refresh()->canal_push_statut)->toBe(StatutEnvoi::Envoye)
            ->and(Http::recorded(fn ($r) => str_contains($r->url(), 'oauth2.test')))->toHaveCount(2);
    });

    it('compte les essais sur panne FCM puis abandonne au maximum', function () {
        configurerFcm();
        config(['sopi.notifications.max_tentatives' => 2]);
        fauxFcm([Http::response(['error' => ['status' => 'UNAVAILABLE']], 503), Http::response(['error' => ['status' => 'UNAVAILABLE']], 503)]);
        $user = utilisateurAvecAppareils(['jeton-appareil-000000000001']);
        $n = notificationPush($user);

        app(NotificationService::class)->envoyer($n);
        expect($n->refresh()->canal_push_statut)->toBe(StatutEnvoi::EnAttente)->and($n->dernier_echec_message)->toContain('HTTP 503 (UNAVAILABLE)');
        $n->forceFill(['prochain_essai_le' => null])->save();
        app(NotificationService::class)->envoyer($n);

        expect($n->refresh()->canal_push_statut)->toBe(StatutEnvoi::Echoue)->and($n->tentatives)->toBe(2)->and(Appareil::query()->count())->toBe(1);
    });

    it('ne journalise ni jeton, ni clé, ni jeton d\'accès', function () {
        configurerFcm();
        $journal = [];
        Log::listen(function ($m) use (&$journal) {
            $journal[] = $m->message.json_encode($m->context);
        });
        fauxFcm([Http::response(['error' => ['status' => 'UNAVAILABLE']], 503)]);
        $user = utilisateurAvecAppareils(['jeton-appareil-000000000001']);

        app(NotificationService::class)->envoyer(notificationPush($user));

        $tout = implode("\n", $journal);
        expect($tout)->not->toContain('jeton-appareil-000000000001')->not->toContain('PRIVATE KEY')->not->toContain('jeton-acces-secret')->not->toContain(FCM_EMAIL_COMPTE);
    });

    it('n\'envoie aucun push à un administrateur', function () {
        configurerFcm();
        Http::fake();
        $admin = User::factory()->administrateur()->create();

        expect(app(NotificationService::class)->creer($admin, TypeNotification::LitigeOuvert, 'Litige', 'Un litige.'))->toBeNull();
        Http::assertNothingSent();
    });

    it('retire les jetons d\'un compte banni après son dernier message', function () {
        configurerFcm();
        fauxFcm([Http::response(['name' => 'ok'])]);
        $user = utilisateurAvecAppareils(['jeton-appareil-000000000001'], ['statut' => StatutCompte::Banni]);

        app(NotificationService::class)->envoyer(notificationPush($user));

        expect(Appareil::query()->count())->toBe(0);
    });
});

describe('routes /appareils', function () {
    $jeton = 'jeton-fcm-exemple-0123456789';

    it('exige une authentification', function () use ($jeton) {
        $this->postJson('/api/v1/appareils', ['jeton' => $jeton, 'plateforme' => 'android'])->assertUnauthorized();
        $this->deleteJson("/api/v1/appareils/$jeton")->assertUnauthorized();
    });

    it('enregistre puis met à jour un appareil sans doublon', function () use ($jeton) {
        $client = User::factory()->create();

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/appareils', ['jeton' => $jeton, 'plateforme' => 'android'])
            ->assertOk()->assertExactJson(['message' => 'Appareil enregistré.']);
        $avant = Appareil::query()->firstOrFail()->derniere_activite;
        $this->travel(5)->minutes();
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/appareils', ['jeton' => $jeton, 'plateforme' => 'ios'])->assertOk();

        $appareil = Appareil::query()->sole();
        expect($appareil->plateforme)->toBe(PlateformeAppareil::Ios)->and($appareil->user_id)->toBe($client->id)
            ->and($appareil->derniere_activite->gt($avant))->toBeTrue();
    });

    it('accepte plusieurs appareils pour un même utilisateur', function () {
        $client = User::factory()->create();
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/appareils', ['jeton' => 'jeton-premier-00000000001', 'plateforme' => 'android'])->assertOk();
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/appareils', ['jeton' => 'jeton-second-000000000002', 'plateforme' => 'ios'])->assertOk();

        expect(Appareil::query()->where('user_id', $client->id)->count())->toBe(2);
    });

    it('donne le jeton au dernier connecté et abandonne les push en attente de l\'ancien', function () use ($jeton) {
        $ancien = User::factory()->create();
        $nouveau = User::factory()->create();
        $this->actingAs($ancien, 'sanctum')->postJson('/api/v1/appareils', ['jeton' => $jeton, 'plateforme' => 'android'])->assertOk();
        $enAttente = notificationPush($ancien);

        $this->actingAs($nouveau, 'sanctum')->postJson('/api/v1/appareils', ['jeton' => $jeton, 'plateforme' => 'android'])->assertOk();

        expect(Appareil::query()->sole()->user_id)->toBe($nouveau->id)
            ->and($enAttente->refresh()->canal_push_statut)->toBe(StatutEnvoi::NonApplicable);
    });

    it('ne laisse pas un autre utilisateur retirer mon appareil', function () use ($jeton) {
        $moi = User::factory()->create();
        $autre = User::factory()->create();
        $this->actingAs($moi, 'sanctum')->postJson('/api/v1/appareils', ['jeton' => $jeton, 'plateforme' => 'android'])->assertOk();

        $this->actingAs($autre, 'sanctum')->deleteJson("/api/v1/appareils/$jeton")->assertOk();
        expect(Appareil::query()->count())->toBe(1);

        $this->actingAs($moi, 'sanctum')->deleteJson("/api/v1/appareils/$jeton")->assertOk()->assertExactJson(['message' => 'Appareil retiré.']);
        expect(Appareil::query()->count())->toBe(0);
    });

    it('refuse un compte non actif', function (StatutCompte $statut) use ($jeton) {
        $user = User::factory()->create(['statut' => $statut]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/appareils', ['jeton' => $jeton, 'plateforme' => 'android'])->assertForbidden()->assertJsonPath('statutCompte', $statut->value);
        expect(Appareil::query()->count())->toBe(0);
    })->with([StatutCompte::Banni, StatutCompte::Suspendu]);

    it('refuse l\'administrateur', function () use ($jeton) {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson('/api/v1/appareils', ['jeton' => $jeton, 'plateforme' => 'android'])->assertForbidden();
    });

    it('valide les champs avec des messages en français', function () {
        $client = User::factory()->create();

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/appareils', ['jeton' => 'court', 'plateforme' => 'windows'])
            ->assertUnprocessable()->assertJsonValidationErrors(['jeton', 'plateforme'])
            ->assertJsonPath('errors.plateforme.0', 'La plateforme doit être android ou ios.');
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/appareils', [])->assertUnprocessable()->assertJsonValidationErrors(['jeton', 'plateforme']);
    });

    it('limite le nombre d\'appels par minute', function () use ($jeton) {
        $client = User::factory()->create();
        $statut = null;
        for ($i = 0; $i < 31; $i++) {
            $statut = $this->actingAs($client, 'sanctum')->postJson('/api/v1/appareils', ['jeton' => $jeton, 'plateforme' => 'android'])->status();
        }

        expect($statut)->toBe(429);
    });

    it('ne renvoie jamais le jeton', function () use ($jeton) {
        $reponse = $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/appareils', ['jeton' => $jeton, 'plateforme' => 'android'])->assertOk();

        expect($reponse->getContent())->not->toContain($jeton);
    });
});

describe('suppression de compte', function () {
    it('supprime les appareils avec l\'utilisateur', function () {
        $user = utilisateurAvecAppareils(['jeton-appareil-000000000001']);
        $user->delete();

        expect(Appareil::query()->count())->toBe(0);
    });
});
