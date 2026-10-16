<?php

use App\Enums\StatutCompte;
use App\Mail\NotificationMail;
use App\Models\ReinitialisationMotDePasse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

require_once __DIR__.'/../../Helpers/notifications_reelles.php';

const MESSAGE_LIEN_KO = 'Ce lien est invalide, expiré ou déjà utilisé. Faites une nouvelle demande de réinitialisation.';

/** Demande un lien pour l'utilisateur et renvoie le jeton lu dans l'e-mail (seul endroit où il existe en clair). */
function jetonDemande(User $user): string
{
    test()->postJson('/api/v1/auth/mot-de-passe-oublie', ['email' => $user->email])->assertOk();
    $jeton = '';
    Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use (&$jeton) {
        if (preg_match('#href="[^"]*/reinitialiser-mot-de-passe/([a-f0-9]{64})"#', $mail->render(), $m)) {
            $jeton = $m[1];
        }

        return true;
    });

    return $jeton;
}

beforeEach(function () {
    Cache::flush();
    configurerSmtp();
    Mail::fake();
});

describe('API de réinitialisation', function () {
    it('change le mot de passe, révoque toutes les sessions et envoie la confirmation sans lien', function () {
        $user = User::factory()->create(['email' => 'awa@exemple.test']);
        $user->createToken('a');
        $user->createToken('b');
        $jeton = jetonDemande($user);
        Mail::fake();

        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', [
            'jeton' => $jeton, 'mot_de_passe' => 'NouveauPass123', 'mot_de_passe_confirmation' => 'NouveauPass123',
        ])->assertOk()->assertJsonPath('message', 'Votre mot de passe a été modifié. Vous pouvez vous reconnecter.');

        expect(password_verify('NouveauPass123', $user->fresh()->password))->toBeTrue()
            ->and($user->tokens()->count())->toBe(0)
            ->and(ReinitialisationMotDePasse::query()->whereNull('utilise_le')->count())->toBe(0);
        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) {
            expect($mail->hasTo('awa@exemple.test'))->toBeTrue()
                ->and($mail->envelope()->subject)->toBe('Mot de passe modifié')
                ->and($mail->render())->not->toContain('reinitialiser-mot-de-passe');

            return true;
        });
    });

    it('refuse un jeton déjà utilisé', function () {
        $user = User::factory()->create(['email' => 'awa@exemple.test']);
        $jeton = jetonDemande($user);
        $corps = ['jeton' => $jeton, 'mot_de_passe' => 'NouveauPass123', 'mot_de_passe_confirmation' => 'NouveauPass123'];

        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', $corps)->assertOk();
        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', $corps)
            ->assertStatus(422)->assertJsonPath('errors.jeton.0', MESSAGE_LIEN_KO);
    });

    it('refuse un jeton expiré ou inconnu', function () {
        $user = User::factory()->create(['email' => 'awa@exemple.test']);
        $jeton = jetonDemande($user);
        ReinitialisationMotDePasse::query()->update(['expire_le' => now()->subMinute()]);
        $corps = ['mot_de_passe' => 'NouveauPass123', 'mot_de_passe_confirmation' => 'NouveauPass123'];

        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', $corps + ['jeton' => $jeton])->assertStatus(422);
        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', $corps + ['jeton' => str_repeat('a', 64)])->assertStatus(422);
        expect(password_verify('NouveauPass123', $user->fresh()->password))->toBeFalse();
    });

    it('invalide l\'ancien jeton quand une nouvelle demande est faite', function () {
        $user = User::factory()->create(['email' => 'awa@exemple.test']);
        $ancien = jetonDemande($user);
        Mail::fake();
        $nouveau = jetonDemande($user);
        $corps = ['mot_de_passe' => 'NouveauPass123', 'mot_de_passe_confirmation' => 'NouveauPass123'];

        expect($ancien)->not->toBe($nouveau);
        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', $corps + ['jeton' => $ancien])->assertStatus(422);
        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', $corps + ['jeton' => $nouveau])->assertOk();
    });

    it('applique les règles de mot de passe en français', function () {
        $jeton = jetonDemande(User::factory()->create(['email' => 'awa@exemple.test']));

        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', ['jeton' => $jeton, 'mot_de_passe' => 'court', 'mot_de_passe_confirmation' => 'court'])
            ->assertStatus(422)->assertJsonValidationErrors('mot_de_passe');
        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', ['jeton' => $jeton, 'mot_de_passe' => 'NouveauPass123', 'mot_de_passe_confirmation' => 'Autre12345'])
            ->assertStatus(422)->assertJsonValidationErrors('mot_de_passe');
    });

    it('refuse si le compte n\'est plus actif', function () {
        $user = User::factory()->create(['email' => 'awa@exemple.test']);
        $jeton = jetonDemande($user);
        $user->forceFill(['statut' => StatutCompte::Banni])->save();

        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', ['jeton' => $jeton, 'mot_de_passe' => 'NouveauPass123', 'mot_de_passe_confirmation' => 'NouveauPass123'])
            ->assertStatus(422);
    });

    it('limite les essais par jeton', function () {
        $corps = ['jeton' => str_repeat('b', 64), 'mot_de_passe' => 'NouveauPass123', 'mot_de_passe_confirmation' => 'NouveauPass123'];
        $limite = config('securite.reinitialisation.max_validations_par_heure_par_jeton');

        for ($i = 0; $i < $limite; $i++) {
            $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', $corps)->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', $corps)->assertStatus(429);
    });
});

describe('page web de réinitialisation', function () {
    it('affiche le formulaire avec les bons en-têtes de confidentialité', function () {
        $jeton = jetonDemande(User::factory()->create(['email' => 'awa@exemple.test']));

        $reponse = $this->get('/reinitialiser-mot-de-passe/'.$jeton)->assertOk();
        $reponse->assertSee('Choisissez un nouveau mot de passe')
            ->assertSee('name="mot_de_passe"', false)
            ->assertSee('name="mot_de_passe_confirmation"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('noindex', false);
        expect($reponse->headers->get('Cache-Control'))->toContain('no-store')
            ->and($reponse->headers->get('Referrer-Policy'))->toBe('no-referrer')
            ->and($reponse->headers->get('X-Robots-Tag'))->toContain('noindex');
    });

    it('affiche une page d\'erreur claire pour un jeton inconnu ou expiré', function () {
        $this->get('/reinitialiser-mot-de-passe/'.str_repeat('c', 64))
            ->assertNotFound()->assertSee('Lien invalide ou expiré')->assertSee('Mot de passe oublié');

        $jeton = jetonDemande(User::factory()->create(['email' => 'awa@exemple.test']));
        ReinitialisationMotDePasse::query()->update(['expire_le' => now()->subMinute()]);
        $this->get('/reinitialiser-mot-de-passe/'.$jeton)->assertNotFound()->assertSee('Lien invalide ou expiré');
    });

    it('enregistre le nouveau mot de passe et affiche la page de succès', function () {
        $user = User::factory()->create(['email' => 'awa@exemple.test']);
        $user->createToken('a');
        $jeton = jetonDemande($user);

        $this->post('/reinitialiser-mot-de-passe/'.$jeton, ['mot_de_passe' => 'NouveauPass123', 'mot_de_passe_confirmation' => 'NouveauPass123'])
            ->assertOk()->assertSee('Mot de passe modifié');

        expect(password_verify('NouveauPass123', $user->fresh()->password))->toBeTrue()->and($user->tokens()->count())->toBe(0);
        $this->get('/reinitialiser-mot-de-passe/'.$jeton)->assertNotFound();
    });

    it('réaffiche le formulaire avec les erreurs en français sans renvoyer le mot de passe', function () {
        $jeton = jetonDemande(User::factory()->create(['email' => 'awa@exemple.test']));

        $reponse = $this->post('/reinitialiser-mot-de-passe/'.$jeton, ['mot_de_passe' => 'court', 'mot_de_passe_confirmation' => 'autre'])
            ->assertStatus(422)->assertSee('role="alert"', false);
        expect($reponse->getContent())->not->toContain('value="court"');
    });

    it('limite les ouvertures de la page par jeton', function () {
        $url = '/reinitialiser-mot-de-passe/'.str_repeat('d', 64);
        $limite = config('securite.reinitialisation.max_validations_par_heure_par_jeton');

        for ($i = 0; $i < $limite; $i++) {
            $this->get($url)->assertNotFound();
        }
        $this->get($url)->assertStatus(429)->assertSee('Trop de tentatives');
    });
});
