<?php

use App\Enums\StatutEnvoi;
use App\Enums\TypeNotification;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const URL_NOTIFICATIONS_ECHEC = '/api/v1/admin/notifications-en-echec';

beforeEach(fn () => Carbon::setTestNow('2030-01-20 12:00:00'));

function idsNotificationsEnEchec(string $requete = ''): array
{
    return collect(test()->getJson(URL_NOTIFICATIONS_ECHEC.$requete)->assertOk()->json('data'))->pluck('id')->all();
}

describe('notifications en échec — accès', function () {
    it('exige une authentification', function () {
        $this->getJson(URL_NOTIFICATIONS_ECHEC)->assertUnauthorized();
        $this->getJson(URL_NOTIFICATIONS_ECHEC.'/'.Notification::factory()->create()->id)->assertUnauthorized();
    });

    it('refuse un client et un prestataire (403)', function () {
        $cible = Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue]);
        foreach ([User::factory()->create(), User::factory()->prestataire()->create()] as $intrus) {
            $this->actingAs($intrus, 'sanctum');
            $this->getJson(URL_NOTIFICATIONS_ECHEC)->assertForbidden();
            $this->getJson(URL_NOTIFICATIONS_ECHEC.'/'.$cible->id)->assertForbidden();
        }
    });
});

describe('notifications en échec — liste', function () {
    it('renvoie une liste vide', function () {
        adminConnecte();

        $this->getJson(URL_NOTIFICATIONS_ECHEC)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    });

    it('ne renvoie que les notifications dont un canal a échoué', function () {
        adminConnecte();
        $email = Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue]);
        $push = Notification::factory()->create(['canal_push_statut' => StatutEnvoi::Echoue]);
        Notification::factory()->create();                // tout envoyé
        Notification::factory()->enAttente()->create();   // en cours d'envoi
        Notification::factory()->create(['canal_email_statut' => StatutEnvoi::NonApplicable, 'canal_push_statut' => StatutEnvoi::NonApplicable]);

        expect(idsNotificationsEnEchec())->toEqualCanonicalizing([$email->id, $push->id]);
    });

    it('renvoie la forme exacte d\'une ligne', function () {
        adminConnecte();
        $destinataire = User::factory()->prestataire()->create();
        $notification = Notification::factory()->create([
            'user_id' => $destinataire->id, 'type' => TypeNotification::ReservationAcceptee,
            'canal_email_statut' => StatutEnvoi::Echoue, 'canal_push_statut' => StatutEnvoi::Echoue,
            'tentatives' => 5, 'dernier_echec_classe' => 'TransportException', 'created_at' => '2030-01-20 08:00:00',
        ]);

        $this->getJson(URL_NOTIFICATIONS_ECHEC)->assertOk()->assertJsonPath('data.0', [
            'id' => $notification->id,
            'type' => 'reservation_acceptee',
            'destinataire' => ['id' => $destinataire->id, 'role' => 'prestataire'],
            'canauxEnEchec' => ['email', 'push'],
            'tentatives' => 5,
            'classeErreur' => 'TransportException',
            'messageErreur' => null,
            'dateCreation' => '2030-01-20T08:00:00+00:00',
        ]);
    });

    it('n\'indique que les canaux réellement en échec', function () {
        adminConnecte();
        Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Envoye, 'canal_push_statut' => StatutEnvoi::Echoue]);

        $this->getJson(URL_NOTIFICATIONS_ECHEC)->assertOk()->assertJsonPath('data.0.canauxEnEchec', ['push']);
    });

    it('n\'expose ni le message, ni les coordonnées du destinataire', function () {
        adminConnecte();
        $destinataire = User::factory()->create([
            'email' => 'secret.destinataire@exemple.test', 'telephone' => '+221779998877', 'prenom' => 'Awa', 'nom' => 'Ndiayeunique',
        ]);
        Notification::factory()->create([
            'user_id' => $destinataire->id, 'canal_email_statut' => StatutEnvoi::Echoue, 'canal_push_statut' => StatutEnvoi::Echoue,
            'titre' => 'Titre confidentiel', 'corps' => 'Corps confidentiel du message.', 'donnees' => ['code' => 'ABC123'],
        ]);

        $corps = strtolower($this->getJson(URL_NOTIFICATIONS_ECHEC)->assertOk()->getContent());
        foreach (['secret.destinataire', '779998877', 'awa', 'ndiayeunique', 'titre confidentiel', 'corps confidentiel', 'abc123', 'password', 'token', 'telephone'] as $interdit) {
            expect($corps)->not->toContain($interdit);
        }
        $ligne = $this->getJson(URL_NOTIFICATIONS_ECHEC)->json('data.0');
        expect(array_keys($ligne))->toBe(['id', 'type', 'destinataire', 'canauxEnEchec', 'tentatives', 'classeErreur', 'messageErreur', 'dateCreation'])
            ->and(array_keys($ligne['destinataire']))->toBe(['id', 'role']);
    });

    it('trie du plus récent au plus ancien et pagine par 20', function () {
        adminConnecte();
        foreach (range(1, 25) as $i) {
            Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue, 'created_at' => Carbon::parse('2030-01-01 00:00:00')->addMinutes($i)]);
        }

        $page1 = $this->getJson(URL_NOTIFICATIONS_ECHEC)->assertOk();
        expect($page1->json('data'))->toHaveCount(20)->and($page1->json('meta.total'))->toBe(25)
            ->and($page1->json('data.0.dateCreation'))->toBe('2030-01-01T00:25:00+00:00');
        expect($this->getJson(URL_NOTIFICATIONS_ECHEC.'?page=2')->json('data'))->toHaveCount(5);
    });

    it('filtre par canal en échec', function () {
        adminConnecte();
        $email = Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue]);
        $push = Notification::factory()->create(['canal_push_statut' => StatutEnvoi::Echoue]);
        $deux = Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue, 'canal_push_statut' => StatutEnvoi::Echoue]);

        expect(idsNotificationsEnEchec('?canal=email'))->toEqualCanonicalizing([$email->id, $deux->id])
            ->and(idsNotificationsEnEchec('?canal=push'))->toEqualCanonicalizing([$push->id, $deux->id]);
    });

    it('filtre par type', function () {
        adminConnecte();
        $voulue = Notification::factory()->create(['type' => TypeNotification::LitigeOuvert, 'canal_email_statut' => StatutEnvoi::Echoue]);
        Notification::factory()->create(['type' => TypeNotification::AvisRecu, 'canal_email_statut' => StatutEnvoi::Echoue]);

        expect(idsNotificationsEnEchec('?type=litige_ouvert'))->toBe([$voulue->id])
            ->and(idsNotificationsEnEchec('?type=sanction_levee'))->toBe([]);
    });

    it('filtre par ancienneté minimale en minutes (borne incluse)', function () {
        adminConnecte();
        $vieille = Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue, 'created_at' => now()->subHours(3)]);
        $pile = Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue, 'created_at' => now()->subMinutes(60)]);
        Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue, 'created_at' => now()->subMinutes(59)]);

        expect(idsNotificationsEnEchec('?ancienneteMinutes=60'))->toEqualCanonicalizing([$vieille->id, $pile->id])
            ->and(idsNotificationsEnEchec('?anciennete_minutes=60'))->toEqualCanonicalizing([$vieille->id, $pile->id]);
    });

    it('combine les filtres', function () {
        adminConnecte();
        $voulue = Notification::factory()->create(['type' => TypeNotification::LitigeOuvert, 'canal_push_statut' => StatutEnvoi::Echoue, 'created_at' => now()->subHours(2)]);
        Notification::factory()->create(['type' => TypeNotification::LitigeOuvert, 'canal_email_statut' => StatutEnvoi::Echoue, 'created_at' => now()->subHours(2)]); // mauvais canal
        Notification::factory()->create(['type' => TypeNotification::AvisRecu, 'canal_push_statut' => StatutEnvoi::Echoue, 'created_at' => now()->subHours(2)]);      // mauvais type
        Notification::factory()->create(['type' => TypeNotification::LitigeOuvert, 'canal_push_statut' => StatutEnvoi::Echoue, 'created_at' => now()->subMinutes(5)]);  // trop récente

        expect(idsNotificationsEnEchec('?canal=push&type=litige_ouvert&ancienneteMinutes=60'))->toBe([$voulue->id]);
    });

    it('rejette des filtres invalides en français (422)', function (string $requete, string $champ, string $message) {
        adminConnecte();

        $this->getJson(URL_NOTIFICATIONS_ECHEC.$requete)->assertUnprocessable()->assertJsonPath("errors.$champ.0", $message);
    })->with([
        'canal' => ['?canal=sms', 'canal', 'Le canal doit être email ou push.'],
        'type' => ['?type=inconnu', 'type', 'Le type de notification est inconnu.'],
        'ancienneté texte' => ['?ancienneteMinutes=abc', 'ancienneteMinutes', 'L\'ancienneté doit être un nombre entier de minutes.'],
        'ancienneté nulle' => ['?ancienneteMinutes=0', 'ancienneteMinutes', 'L\'ancienneté doit être d\'au moins 1 minute.'],
        'ancienneté trop grande' => ['?ancienneteMinutes=525601', 'ancienneteMinutes', 'L\'ancienneté ne peut pas dépasser 525600 minutes (un an).'],
        'page' => ['?page=0', 'page', 'La page doit être au moins 1.'],
    ]);

    it('exécute un nombre de requêtes constant quel que soit le volume', function () {
        adminConnecte();
        Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue]);
        $petit = nombreDeRequetes(fn () => $this->getJson(URL_NOTIFICATIONS_ECHEC)->assertOk());

        Notification::factory()->count(10)->create(['canal_push_statut' => StatutEnvoi::Echoue]);
        $grand = nombreDeRequetes(fn () => $this->getJson(URL_NOTIFICATIONS_ECHEC)->assertOk());

        expect($grand)->toBe($petit);
    });
});

describe('notifications en échec — détail', function () {
    it('répond 404 pour un identifiant inconnu ou mal formé', function () {
        adminConnecte();

        foreach (['0198a1b2-0000-7000-8000-00000000ffff', 'pas-un-uuid'] as $id) {
            $this->getJson(URL_NOTIFICATIONS_ECHEC.'/'.$id)->assertNotFound()->assertExactJson(['message' => 'Notification introuvable.']);
        }
    });

    it('répond 404 pour une notification qui n\'est pas en échec', function () {
        adminConnecte();
        $envoyee = Notification::factory()->create();

        $this->getJson(URL_NOTIFICATIONS_ECHEC.'/'.$envoyee->id)->assertNotFound()->assertExactJson(['message' => 'Notification introuvable.']);
    });

    it('renvoie la même forme que la liste, sans le message', function () {
        adminConnecte();
        $notification = Notification::factory()->create([
            'canal_email_statut' => StatutEnvoi::Echoue, 'tentatives' => 3, 'dernier_echec_classe' => 'TransportException',
            'titre' => 'Titre confidentiel', 'created_at' => '2030-01-20 08:00:00',
        ]);

        $reponse = $this->getJson(URL_NOTIFICATIONS_ECHEC.'/'.$notification->id)->assertOk();
        $reponse->assertExactJson([
            'id' => $notification->id,
            'type' => 'reservation_confirmee',
            'destinataire' => ['id' => $notification->user_id, 'role' => 'client'],
            'canauxEnEchec' => ['email'],
            'tentatives' => 3,
            'classeErreur' => 'TransportException',
            'messageErreur' => null,
            'dateCreation' => '2030-01-20T08:00:00+00:00',
        ]);
        expect($reponse->getContent())->not->toContain('confidentiel');
    });

    it('exécute un nombre de requêtes constant', function () {
        adminConnecte();
        $a = Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue]);
        $b = Notification::factory()->create(['canal_push_statut' => StatutEnvoi::Echoue]);

        expect(nombreDeRequetes(fn () => $this->getJson(URL_NOTIFICATIONS_ECHEC.'/'.$b->id)->assertOk()))
            ->toBe(nombreDeRequetes(fn () => $this->getJson(URL_NOTIFICATIONS_ECHEC.'/'.$a->id)->assertOk()));
    });
});
