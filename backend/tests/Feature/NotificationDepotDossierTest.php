<?php

use App\Enums\Canal;
use App\Enums\StatutCompte;
use App\Enums\StatutEnvoi;
use App\Enums\TypeNotification;
use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\Prestataire;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

require_once __DIR__.'/../Helpers/notifications_reelles.php';

beforeEach(fn () => Storage::fake('local'));

function depotDossier(array $surcharge = []): TestResponse
{
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    return test()->postJson('/api/v1/prestataires/inscription', array_merge([
        'nom' => 'Diop',
        'prenom' => 'Moussa',
        'telephone' => '77 123 45 67',
        'email' => 'moussa@exemple.test',
        'mot_de_passe' => 'mot-de-passe-exemple',
        'mot_de_passe_confirmation' => 'mot-de-passe-exemple',
        'nom_entreprise' => 'Garage Moussa',
        'metier' => 'mecanique',
        'quartier' => 'Malika',
        'adresse' => 'Rue 10, près du marché',
        'justificatifs' => [
            ['type_document' => 'cni', 'fichier' => UploadedFile::fake()->createWithContent('cni.pdf', $pdf)],
            ['type_document' => 'diplome', 'fichier' => UploadedFile::fake()->createWithContent('diplome.pdf', $pdf)],
        ],
    ], $surcharge));
}

/** Tout ce que la base contient, en texte : sert à prouver l'absence du jeton en clair. */
function contenuDeLaBase(): string
{
    $texte = '';
    foreach (['notifications', 'prestataires', 'users', 'justificatifs', 'jobs', 'failed_jobs', 'cache'] as $table) {
        $texte .= json_encode(DB::table($table)->get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return $texte;
}

it('envoie au dépôt un e-mail français avec le lien de suivi personnel', function () {
    configurerSmtp();
    Mail::fake();

    $reponse = depotDossier()->assertCreated();
    $lien = $reponse->json('lien_suivi');

    Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($lien) {
        $html = $mail->render();
        $texte = view($mail->content()->text, $mail->content()->with)->render();
        expect($mail->hasTo('moussa@exemple.test'))->toBeTrue()
            ->and($mail->envelope()->subject)->toBe('Dossier bien reçu')
            ->and($html)->toContain('lang="fr"')->toContain('Bonjour Moussa')->toContain('Garage Moussa')->toContain('Suivre mon dossier')
            ->toContain('href="'.$lien.'"')->toContain('validation de votre compte')
            ->and($texte)->toContain($lien);

        return true;
    });
    $notification = Notification::sole();
    expect($notification->type)->toBe(TypeNotification::DossierDepose)
        ->and($notification->canal_email_statut)->toBe(StatutEnvoi::Envoye)
        ->and($notification->canal_push_statut)->toBe(StatutEnvoi::NonApplicable)
        ->and($notification->secret_chiffre)->toBeNull();
});

it('envoie un lien qui ouvre bien la route de suivi', function () {
    configurerSmtp();
    Mail::fake();

    $lien = depotDossier()->assertCreated()->json('lien_suivi');
    Mail::assertSent(NotificationMail::class, fn (NotificationMail $m) => str_contains($m->render(), 'href="'.$lien.'"'));

    $this->getJson(parse_url($lien, PHP_URL_PATH))->assertOk()
        ->assertJsonPath('statutCompte', 'en_attente')->assertJsonPath('nomEntreprise', 'Garage Moussa');
});

it('garde la réponse 201 avec jeton_suivi et lien_suivi cohérents', function () {
    configurerSmtp();
    Mail::fake();

    $reponse = depotDossier()->assertCreated()->assertJsonStructure(['jeton_suivi', 'lien_suivi']);

    expect($reponse->json('lien_suivi'))->toEndWith('/api/v1/suivi/'.$reponse->json('jeton_suivi'));
});

it('ne bloque pas l\'inscription si l\'envoi échoue : la notification reste en attente puis est retentée', function () {
    configurerSmtp(['mail.mailers.smtp.host' => null]);
    Mail::fake();

    $reponse = depotDossier()->assertCreated();
    $jeton = $reponse->json('jeton_suivi');

    $notification = Notification::sole();
    expect($notification->canal_email_statut)->toBe(StatutEnvoi::EnAttente)
        ->and($notification->tentatives)->toBe(1)
        ->and($notification->dernier_echec_message)->toContain('MAIL_HOST')->not->toContain($jeton)
        ->and($notification->secret_chiffre)->not->toBeNull()->not->toContain($jeton)
        ->and(Prestataire::count())->toBe(1);
    Mail::assertNothingSent();

    // La configuration est corrigée : la reprise planifiée envoie le message avec le même lien, puis efface le secret.
    configurerSmtp();
    $this->travel(10)->minutes();
    app(NotificationService::class)->envoyerEnAttente(10);

    Mail::assertSent(NotificationMail::class, fn (NotificationMail $m) => str_contains($m->render(), $jeton));
    $notification->refresh();
    expect($notification->canal_email_statut)->toBe(StatutEnvoi::Envoye)->and($notification->secret_chiffre)->toBeNull();
});

it('ne bloque pas l\'inscription si la création de la notification échoue', function () {
    $this->mock(NotificationService::class, fn ($mock) => $mock->shouldReceive('creer')->andThrow(new RuntimeException('panne')));

    depotDossier()->assertCreated();

    expect(Prestataire::count())->toBe(1)->and(User::where('email', 'moussa@exemple.test')->exists())->toBeTrue();
});

it('efface le secret après l\'échec définitif', function () {
    configurerSmtp(['mail.mailers.smtp.host' => null]);
    Mail::fake();
    depotDossier()->assertCreated();
    $notification = Notification::sole();
    expect($notification->secret_chiffre)->not->toBeNull();

    for ($i = 1; $i < (int) config('sopi.notifications.max_tentatives'); $i++) {
        $this->travel(10)->minutes();
        app(NotificationService::class)->envoyerEnAttente(10);
    }

    $notification->refresh();
    expect($notification->canal_email_statut)->toBe(StatutEnvoi::Echoue)
        ->and($notification->tentatives)->toBe((int) config('sopi.notifications.max_tentatives'))
        ->and($notification->secret_chiffre)->toBeNull();
});

it('ne laisse le jeton en clair ni en base, ni dans les logs, ni dans les erreurs', function () {
    configurerSmtp();
    $journal = [];
    Log::listen(function ($message) use (&$journal) {
        $journal[] = $message->message.json_encode($message->context);
    });
    // Le transport échoue en recopiant le lien et l'adresse dans son message d'erreur.
    $jeton = 'inconnu';
    Mail::shouldReceive('to')->andReturnUsing(function () use (&$jeton) {
        throw new RuntimeException('Échec pour https://api.exemple.test/api/v1/suivi/'.$jeton.' vers moussa@exemple.test');
    });

    $jeton = depotDossier()->assertCreated()->json('jeton_suivi');
    $this->travel(10)->minutes();
    app(NotificationService::class)->envoyerEnAttente(10);

    $notification = Notification::sole();
    expect($notification->tentatives)->toBe(2)
        ->and($notification->dernier_echec_message)->toContain('/suivi/[masqué]')
        ->and(contenuDeLaBase())->not->toContain($jeton)
        ->and(implode("\n", $journal))->not->toContain($jeton)->not->toContain('moussa@exemple.test')
        ->and($notification->dernier_echec_message)->not->toContain($jeton)->not->toContain('moussa@exemple.test')
        ->and($notification->corps)->not->toContain($jeton)
        ->and($notification->titre)->not->toContain($jeton)
        ->and(json_encode($notification->donnees))->not->toContain($jeton);
});

it('n\'expose le jeton ni dans l\'écran des notifications en échec ni dans l\'API du destinataire', function () {
    configurerSmtp(['mail.mailers.smtp.host' => null]);
    Mail::fake();
    $jeton = depotDossier()->assertCreated()->json('jeton_suivi');
    $notification = Notification::sole();
    Notification::query()->whereKey($notification->id)->update(['canal_email_statut' => StatutEnvoi::Echoue->value]);

    $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
    $liste = $this->getJson('/api/v1/admin/notifications-en-echec')->assertOk()->getContent();
    $detail = $this->getJson('/api/v1/admin/notifications-en-echec/'.$notification->id)->assertOk()->getContent();
    expect($liste)->not->toContain($jeton)->not->toContain('secret')->and($detail)->not->toContain($jeton)->not->toContain('secret');

    // Côté destinataire (compte activé plus tard) : la liste des notifications ne contient ni jeton ni secret.
    $notification->user->forceFill(['statut' => StatutCompte::Actif])->save();
    $this->actingAs($notification->user->refresh(), 'sanctum');
    $api = $this->getJson('/api/v1/notifications')->assertOk()->getContent();
    expect($api)->not->toContain($jeton)->not->toContain('secret')->not->toContain('suivi/')
        ->and($notification->fresh()->toArray())->not->toHaveKey('secret_chiffre');
});

it('n\'envoie jamais ce message à un administrateur', function () {
    configurerSmtp();
    Mail::fake();
    $admin = User::factory()->administrateur()->create();

    $resultat = app(NotificationService::class)->creer($admin, TypeNotification::DossierDepose, 'Dossier bien reçu', 'Texte', [], [Canal::Email], 'https://api.exemple.test/api/v1/suivi/abc');

    expect($resultat)->toBeNull()->and(Notification::count())->toBe(0);
    Mail::assertNothingSent();
});

it('refuse de composer un e-mail sans lien lisible', function () {
    $user = User::factory()->prestataire()->create(['email' => 'moussa@exemple.test']);
    $notification = app(NotificationService::class)->creer($user, TypeNotification::DossierDepose, 'Dossier bien reçu', 'Texte', [], [Canal::Email]);

    expect(fn () => (new NotificationMail($notification, $user))->render())->toThrow(RuntimeException::class, 'Lien de suivi indisponible');
});
