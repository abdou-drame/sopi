<?php

use App\Mail\NotificationMail;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

require_once __DIR__.'/../../Helpers/notifications_reelles.php';

it('fait fonctionner le formulaire avec le contrôle CSRF actif même si SESSION_DRIVER=array', function () {
    Cache::flush();
    configurerSmtp();
    Mail::fake();
    $user = User::factory()->create(['email' => 'awa@exemple.test', 'password' => 'AncienPass123']);
    $this->postJson('/api/v1/auth/mot-de-passe-oublie', ['email' => 'awa@exemple.test'])->assertOk();
    $jeton = '';
    Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use (&$jeton) {
        preg_match('#/reinitialiser-mot-de-passe/([a-f0-9]{64})"#', $mail->render(), $m);
        $jeton = $m[1] ?? '';

        return true;
    });

    // Simule la production : environnement hors tests (CSRF actif) avec le pilote « array » hérité de l'ancien .env.example.
    $this->app['env'] = 'production';
    config(['session.driver' => 'array']);
    (new AppServiceProvider($this->app))->boot();
    expect(config('session.driver'))->toBe('cookie');

    $url = '/reinitialiser-mot-de-passe/'.$jeton;
    $get = $this->get($url)->assertOk();
    preg_match('/name="_token" value="([^"]+)"/', $get->getContent(), $m);
    $cookies = collect($get->headers->getCookies())->mapWithKeys(fn ($c) => [$c->getName() => $c->getValue()])->all();
    expect($m[1] ?? '')->not->toBe('')->and($cookies)->not->toBeEmpty();

    $corps = ['mot_de_passe' => 'NouveauPass123', 'mot_de_passe_confirmation' => 'NouveauPass123'];
    // Sans jeton CSRF : refus lisible (419).
    $this->post($url, $corps)->assertStatus(419)->assertSee('Cette page a expiré');
    // Avec le jeton et le cookie de session reçus au GET : accepté.
    $this->withUnencryptedCookies($cookies)->post($url, $corps + ['_token' => $m[1]])->assertOk()->assertSee('Mot de passe modifié');
    expect(password_verify('NouveauPass123', $user->fresh()->password))->toBeTrue();
});
