<?php

use App\Enums\StatutCompte;
use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\ReinitialisationMotDePasse;
use App\Models\User;
use App\Services\ReinitialisationMotDePasseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

require_once __DIR__.'/../../Helpers/notifications_reelles.php';

const MESSAGE_OUBLI = 'Si un compte correspond à cette adresse, un lien de réinitialisation vient d\'être envoyé.';

function demanderReinitialisation(string $email, array $entetes = [])
{
    return test()->postJson('/api/v1/auth/mot-de-passe-oublie', ['email' => $email], $entetes);
}

/** Extrait le jeton du lien contenu dans l'e-mail envoyé (le seul endroit où il existe en clair). */
function jetonDuMail(NotificationMail $mail): string
{
    preg_match('#href="[^"]*/reinitialiser-mot-de-passe/([a-f0-9]{64})"#', $mail->render(), $m);

    return $m[1] ?? '';
}

beforeEach(function () {
    Cache::flush();
    configurerSmtp();
    Mail::fake();
});

describe('demande de réinitialisation', function () {
    it('répond toujours la même chose, que l\'adresse existe ou non', function () {
        User::factory()->create(['email' => 'awa@exemple.test']);

        $existe = demanderReinitialisation('awa@exemple.test');
        $inconnue = demanderReinitialisation('personne@exemple.test');

        $existe->assertOk()->assertExactJson(['message' => MESSAGE_OUBLI]);
        $inconnue->assertOk()->assertExactJson(['message' => MESSAGE_OUBLI]);
        expect($existe->getContent())->toBe($inconnue->getContent());
    });

    it('envoie un e-mail avec un lien valide à un compte actif, adresse en majuscules acceptée', function () {
        User::factory()->create(['prenom' => 'Awa', 'email' => 'awa@exemple.test']);

        demanderReinitialisation('  AWA@Exemple.test ')->assertOk();

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) {
            $texte = view($mail->content()->text, $mail->content()->with)->render();
            expect($mail->hasTo('awa@exemple.test'))->toBeTrue()
                ->and($mail->envelope()->subject)->toBe('Réinitialisation de votre mot de passe')
                ->and($mail->render())->toContain('lang="fr"')->toContain('Bonjour Awa')->toContain('Choisir un nouveau mot de passe')->toContain('60 minutes')
                ->and(jetonDuMail($mail))->toHaveLength(64)
                ->and($texte)->toContain(ReinitialisationMotDePasseService::lien(jetonDuMail($mail)));

            return true;
        });
    });

    it('ne stocke que le hachage du jeton, avec une durée de 60 minutes, et efface le secret après l\'envoi', function () {
        $this->travelTo(now()->startOfDay()->addHours(10));
        User::factory()->create(['email' => 'awa@exemple.test']);

        demanderReinitialisation('awa@exemple.test')->assertOk();

        $jeton = '';
        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use (&$jeton) {
            $jeton = jetonDuMail($mail);

            return true;
        });
        $ligne = ReinitialisationMotDePasse::sole();
        expect($ligne->jeton_hash)->toBe(hash('sha256', $jeton))
            ->and($ligne->utilise_le)->toBeNull()
            ->and($ligne->expire_le->equalTo(now()->addMinutes(60)))->toBeTrue();

        $base = '';
        foreach (['reinitialisations_mot_de_passe', 'notifications', 'users', 'jobs', 'failed_jobs', 'cache'] as $table) {
            $base .= json_encode(DB::table($table)->get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        expect($base)->not->toContain($jeton)->and(Notification::sole()->secret_chiffre)->toBeNull();
    });

    it('ne journalise ni le jeton ni l\'adresse', function () {
        Log::spy();
        User::factory()->create(['email' => 'awa@exemple.test']);

        demanderReinitialisation('awa@exemple.test')->assertOk();

        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('info');
    });

    it('invalide le jeton précédent à chaque nouvelle demande', function () {
        User::factory()->create(['email' => 'awa@exemple.test']);

        demanderReinitialisation('awa@exemple.test')->assertOk();
        demanderReinitialisation('awa@exemple.test')->assertOk();

        $lignes = ReinitialisationMotDePasse::orderBy('created_at')->get();
        expect($lignes)->toHaveCount(2)
            ->and($lignes->where('utilise_le', null))->toHaveCount(1);
    });

    it('n\'envoie aucun e-mail aux comptes non éligibles, avec la même réponse', function (string $cas) {
        $fabrique = User::factory()->state(['email' => 'cible@exemple.test']);
        match ($cas) {
            'banni' => $fabrique->statut(StatutCompte::Banni)->create(),
            'suspendu' => $fabrique->statut(StatutCompte::Suspendu)->create(),
            'en attente' => $fabrique->statut(StatutCompte::EnAttenteValidation)->create(),
            'refusé' => $fabrique->statut(StatutCompte::Refuse)->create(),
            'administrateur' => $fabrique->administrateur()->create(),
        };

        demanderReinitialisation('cible@exemple.test')->assertOk()->assertExactJson(['message' => MESSAGE_OUBLI]);

        Mail::assertNothingSent();
        expect(ReinitialisationMotDePasse::count())->toBe(0)->and(Notification::count())->toBe(0);
    })->with(['banni', 'suspendu', 'en attente', 'refusé', 'administrateur']);

    it('ne fait rien pour un client sans adresse e-mail (pas de SMS prévu)', function () {
        User::factory()->sansEmail()->create(['telephone' => '+221771234567']);

        demanderReinitialisation('771234567@exemple.test')->assertOk();

        Mail::assertNothingSent();
        expect(ReinitialisationMotDePasse::count())->toBe(0);
    });

    it('refuse une adresse mal formée avec un message en français', function () {
        demanderReinitialisation('pas-une-adresse')->assertUnprocessable()->assertJsonValidationErrors('email');
        test()->postJson('/api/v1/auth/mot-de-passe-oublie', [])->assertUnprocessable()->assertJsonPath('errors.email.0', 'L\'adresse e-mail est obligatoire.');
    });

    it('porte la durée de la réponse à un minimum fixe (pas de fuite d\'existence par le temps)', function () {
        config(['securite.reinitialisation.duree_minimale_ms' => 150]);
        User::factory()->create(['email' => 'awa@exemple.test']);

        foreach (['awa@exemple.test', 'personne@exemple.test'] as $email) {
            $debut = hrtime(true);
            demanderReinitialisation($email)->assertOk();
            expect((hrtime(true) - $debut) / 1_000_000)->toBeGreaterThanOrEqual(150);
        }
    });

    it('limite les demandes par adresse IP', function () {
        for ($i = 0; $i < 5; $i++) {
            demanderReinitialisation("inconnu{$i}@exemple.test")->assertOk();
        }

        demanderReinitialisation('inconnu9@exemple.test')->assertStatus(429)->assertJsonPath('message', 'Trop de tentatives. Réessayez dans quelques instants.');
    });

    it('limite les demandes par adresse e-mail, même depuis des adresses IP différentes', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/v1/auth/mot-de-passe-oublie', ['email' => 'cible@exemple.test'])->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.9.9'])
            ->postJson('/api/v1/auth/mot-de-passe-oublie', ['email' => 'cible@exemple.test'])->assertStatus(429);
    });
});

describe('confidentialité du jeton', function () {
    it('n\'expose jamais le jeton dans l\'écran des notifications en échec ni dans l\'API', function () {
        configurerSmtp(['mail.mailers.smtp.host' => null]);
        User::factory()->create(['email' => 'awa@exemple.test']);
        $admin = User::factory()->administrateur()->create();

        demanderReinitialisation('awa@exemple.test')->assertOk();

        $notification = Notification::sole();
        $lien = $notification->secret();
        $jeton = basename($lien);
        expect($lien)->toContain('/reinitialiser-mot-de-passe/')->and($jeton)->toMatch('/^[a-f0-9]{64}$/')
            ->and($notification->secret_chiffre)->not->toContain($jeton)
            ->and($notification->corps.$notification->titre.$notification->dernier_echec_message)->not->toContain($jeton);

        $this->actingAs($admin, 'sanctum');
        $liste = $this->getJson('/api/v1/admin/notifications-en-echec')->assertOk();
        $detail = $this->getJson('/api/v1/admin/notifications-en-echec/'.$notification->id);
        expect($liste->getContent())->not->toContain($jeton)->not->toContain('reinitialiser-mot-de-passe')->not->toContain('secret')
            ->and($detail->getContent())->not->toContain($jeton)->not->toContain('reinitialiser-mot-de-passe')->not->toContain('secret');
    });
});
