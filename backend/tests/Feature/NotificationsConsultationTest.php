<?php

use App\Enums\StatutEnvoi;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

describe('consultation des notifications', function () {
    it('exige une authentification', function () {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/non-lues/compteur')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/lire-tout')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/'.fake()->uuid().'/lire')->assertUnauthorized();
    });

    it('refuse l\'administrateur sur toutes les routes (403)', function () {
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/notifications')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/notifications/non-lues/compteur')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/notifications/lire-tout')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/notifications/'.fake()->uuid().'/lire')->assertForbidden();
    });

    it('n\'affiche que mes notifications, des plus récentes aux plus anciennes', function () {
        $moi = User::factory()->create();
        $autre = User::factory()->create();
        $ancienne = Notification::factory()->create(['user_id' => $moi->id, 'titre' => 'Ancienne', 'created_at' => now()->subDay()]);
        $recente = Notification::factory()->create(['user_id' => $moi->id, 'titre' => 'Récente', 'created_at' => now()]);
        Notification::factory()->create(['user_id' => $autre->id, 'titre' => 'Étrangère']);

        $reponse = $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications')->assertOk();

        expect(collect($reponse->json('data'))->pluck('id')->all())->toBe([$recente->id, $ancienne->id])
            ->and($reponse->json('meta.total'))->toBe(2);
    });

    it('un prestataire voit aussi les siennes', function () {
        $prestataire = User::factory()->prestataire()->create();
        Notification::factory()->create(['user_id' => $prestataire->id]);

        $this->actingAs($prestataire, 'sanctum')->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data');
    });

    it('pagine par 20', function () {
        $moi = User::factory()->create();
        Notification::factory()->count(25)->create(['user_id' => $moi->id]);

        $page1 = $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications')->assertOk();
        $page2 = $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications?page=2')->assertOk();

        expect($page1->json('data'))->toHaveCount(20)
            ->and($page2->json('data'))->toHaveCount(5)
            ->and($page1->json('meta.per_page'))->toBe(20)
            ->and($page1->json('meta.total'))->toBe(25)
            ->and($page1->json('meta.last_page'))->toBe(2);
    });

    it('filtre les non lues', function () {
        $moi = User::factory()->create();
        $nonLue = Notification::factory()->create(['user_id' => $moi->id]);
        Notification::factory()->lue()->create(['user_id' => $moi->id]);

        $reponse = $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications?nonLues=true')->assertOk();

        expect(collect($reponse->json('data'))->pluck('id')->all())->toBe([$nonLue->id]);
        $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications?nonLues=false')->assertOk()->assertJsonCount(2, 'data');
    });

    it('refuse un filtre ou une page invalide en français', function () {
        $moi = User::factory()->create();

        $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications?nonLues=peutetre')
            ->assertUnprocessable()->assertJsonPath('errors.nonLues.0', 'Le filtre « non lues » doit être vrai ou faux.');
        $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications?page=0')->assertUnprocessable();
    });

    it('n\'expose aucun champ technique d\'envoi ni identifiant d\'utilisateur', function () {
        $moi = User::factory()->create(['jeton_push' => 'jeton-secret']);
        Notification::factory()->create([
            'user_id' => $moi->id, 'tentatives' => 3, 'dernier_echec_classe' => RuntimeException::class,
            'canal_email_statut' => StatutEnvoi::Echoue, 'prochain_essai_le' => now(),
        ]);

        $reponse = $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications')->assertOk();
        $element = $reponse->json('data.0');

        expect(array_keys($element))->toBe(['id', 'type', 'titre', 'corps', 'donnees', 'lue', 'lueLe', 'dateCreation'])
            ->and($reponse->getContent())->not->toContain('jeton-secret')->not->toContain($moi->id)->not->toContain('tentatives');
    });

    it('renvoie des données vides sous forme d\'objet', function () {
        $moi = User::factory()->create();
        Notification::factory()->create(['user_id' => $moi->id, 'donnees' => null]);

        $brut = $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications')->assertOk()->getContent();

        expect($brut)->toContain('"donnees":{}');
    });

    it('compte les non lues, uniquement les miennes', function () {
        $moi = User::factory()->create();
        Notification::factory()->count(2)->create(['user_id' => $moi->id]);
        Notification::factory()->lue()->create(['user_id' => $moi->id]);
        Notification::factory()->count(4)->create();

        $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications/non-lues/compteur')->assertOk()->assertExactJson(['nonLues' => 2]);
    });
});

describe('marquage comme lue', function () {
    it('marque une notification lue et conserve la date de première lecture', function () {
        $moi = User::factory()->create();
        $n = Notification::factory()->create(['user_id' => $moi->id]);

        $premiere = $this->actingAs($moi, 'sanctum')->postJson("/api/v1/notifications/{$n->id}/lire")
            ->assertOk()->assertJsonPath('lue', true)->assertJsonPath('id', $n->id);
        $dateLecture = $n->fresh()->lue_le;
        expect($dateLecture)->not->toBeNull();

        $this->travel(10)->minutes();
        $this->actingAs($moi, 'sanctum')->postJson("/api/v1/notifications/{$n->id}/lire")->assertOk()->assertJsonPath('lue', true);

        expect($n->fresh()->lue_le->equalTo($dateLecture))->toBeTrue()
            ->and($premiere->json('lueLe'))->not->toBeNull();
    });

    it('répond 404 pour la notification d\'un autre, sans la modifier', function () {
        $moi = User::factory()->create();
        $n = Notification::factory()->create();

        $this->actingAs($moi, 'sanctum')->postJson("/api/v1/notifications/{$n->id}/lire")
            ->assertNotFound()->assertExactJson(['message' => 'Notification introuvable.']);

        expect($n->fresh()->lue_le)->toBeNull();
    });

    it('répond 404 pour un identifiant inconnu ou mal formé', function () {
        $moi = User::factory()->create();

        $this->actingAs($moi, 'sanctum')->postJson('/api/v1/notifications/'.fake()->uuid().'/lire')->assertNotFound();
        $this->actingAs($moi, 'sanctum')->postJson('/api/v1/notifications/pas-un-uuid/lire')
            ->assertNotFound()->assertExactJson(['message' => 'Notification introuvable.']);
    });

    it('ne touche pas aux canaux d\'envoi', function () {
        $moi = User::factory()->create();
        $n = Notification::factory()->enAttente()->create(['user_id' => $moi->id]);

        $this->actingAs($moi, 'sanctum')->postJson("/api/v1/notifications/{$n->id}/lire")->assertOk();

        expect($n->fresh()->canal_email_statut)->toBe(StatutEnvoi::EnAttente);
    });

    it('lire-tout marque seulement les miennes non lues et renvoie leur nombre', function () {
        $moi = User::factory()->create();
        $autre = Notification::factory()->create();
        Notification::factory()->count(3)->create(['user_id' => $moi->id]);
        $dejaLue = Notification::factory()->lue()->create(['user_id' => $moi->id, 'lue_le' => now()->subDay()]);
        $dateInitiale = $dejaLue->fresh()->lue_le;

        $this->actingAs($moi, 'sanctum')->postJson('/api/v1/notifications/lire-tout')->assertOk()->assertExactJson(['marquees' => 3]);
        $this->actingAs($moi, 'sanctum')->postJson('/api/v1/notifications/lire-tout')->assertOk()->assertExactJson(['marquees' => 0]);

        expect(Notification::where('user_id', $moi->id)->whereNull('lue_le')->count())->toBe(0)
            ->and($autre->fresh()->lue_le)->toBeNull()
            ->and($dejaLue->fresh()->lue_le->equalTo($dateInitiale))->toBeTrue();
        $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications/non-lues/compteur')->assertExactJson(['nonLues' => 0]);
    });

    it('applique le limiteur nommé « notifications » (60 par minute)', function () {
        $moi = User::factory()->create();
        RateLimiter::clear($moi->id);

        foreach (range(1, 60) as $i) {
            $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications/non-lues/compteur')->assertOk();
        }
        $this->actingAs($moi, 'sanctum')->getJson('/api/v1/notifications/non-lues/compteur')->assertStatus(429);
    });
});

describe('jeton push', function () {
    it('enregistre, remplace puis retire le jeton, sans jamais le renvoyer', function () {
        $moi = User::factory()->create();

        $r = $this->actingAs($moi, 'sanctum')->putJson('/api/v1/me/jeton-push', ['jetonPush' => 'jeton-un'])
            ->assertOk()->assertExactJson(['message' => 'Jeton de notification enregistré.']);
        expect($moi->fresh()->jeton_push)->toBe('jeton-un')->and($r->getContent())->not->toContain('jeton-un');

        $this->actingAs($moi, 'sanctum')->putJson('/api/v1/me/jeton-push', ['jetonPush' => 'jeton-deux'])->assertOk();
        expect($moi->fresh()->jeton_push)->toBe('jeton-deux');

        $this->actingAs($moi, 'sanctum')->deleteJson('/api/v1/me/jeton-push')
            ->assertOk()->assertExactJson(['message' => 'Jeton de notification retiré.']);
        expect($moi->fresh()->jeton_push)->toBeNull();
    });

    it('ne renvoie jamais le jeton dans le profil', function () {
        $moi = User::factory()->create(['jeton_push' => 'jeton-secret']);

        expect($this->actingAs($moi, 'sanctum')->getJson('/api/v1/me')->assertOk()->getContent())->not->toContain('jeton-secret');
    });

    it('le retrait est idempotent', function () {
        $moi = User::factory()->create();

        $this->actingAs($moi, 'sanctum')->deleteJson('/api/v1/me/jeton-push')->assertOk();
        $this->actingAs($moi, 'sanctum')->deleteJson('/api/v1/me/jeton-push')->assertOk();

        expect($moi->fresh()->jeton_push)->toBeNull();
    });

    it('exige une authentification pour le retrait', function () {
        $this->deleteJson('/api/v1/me/jeton-push')->assertUnauthorized();
    });

    it('ne partage pas un jeton : la dernière affectation l\'emporte et les push en attente de l\'ancien sont abandonnés', function () {
        $ancien = User::factory()->create(['jeton_push' => 'jeton-appareil']);
        $nouveau = User::factory()->create();
        $enAttente = Notification::factory()->enAttente()->create(['user_id' => $ancien->id]);
        $autre = User::factory()->create(['jeton_push' => 'jeton-autre']);

        $this->actingAs($nouveau, 'sanctum')->putJson('/api/v1/me/jeton-push', ['jetonPush' => 'jeton-appareil'])->assertOk();

        expect($ancien->fresh()->jeton_push)->toBeNull()
            ->and($nouveau->fresh()->jeton_push)->toBe('jeton-appareil')
            ->and($autre->fresh()->jeton_push)->toBe('jeton-autre')
            ->and($enAttente->fresh()->canal_push_statut)->toBe(StatutEnvoi::NonApplicable)
            ->and($enAttente->fresh()->canal_email_statut)->toBe(StatutEnvoi::EnAttente)
            ->and(User::where('jeton_push', 'jeton-appareil')->count())->toBe(1);
    });

    it('le retrait abandonne les push en attente mais pas ceux des autres', function () {
        $moi = User::factory()->create(['jeton_push' => 'j']);
        $miennes = Notification::factory()->enAttente()->create(['user_id' => $moi->id]);
        $autres = Notification::factory()->enAttente()->create();

        $this->actingAs($moi, 'sanctum')->deleteJson('/api/v1/me/jeton-push')->assertOk();

        expect($miennes->fresh()->canal_push_statut)->toBe(StatutEnvoi::NonApplicable)
            ->and($autres->fresh()->canal_push_statut)->toBe(StatutEnvoi::EnAttente);
    });

    it('réenregistrer le même jeton pour soi ne change rien', function () {
        $moi = User::factory()->create(['jeton_push' => 'jeton-un']);

        $this->actingAs($moi, 'sanctum')->putJson('/api/v1/me/jeton-push', ['jetonPush' => 'jeton-un'])->assertOk();

        expect($moi->fresh()->jeton_push)->toBe('jeton-un');
    });
});
