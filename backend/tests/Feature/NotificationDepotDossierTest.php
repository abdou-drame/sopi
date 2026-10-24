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
use App\Services\SuiviDossierService;
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

it('envoie un lien qui ouvre bien la page de suivi', function () {
    configurerSmtp();
    Mail::fake();

    $lien = depotDossier()->assertCreated()->json('lien_suivi');
    Mail::assertSent(NotificationMail::class, fn (NotificationMail $m) => str_contains($m->render(), 'href="'.$lien.'"'));

    $this->get(parse_url($lien, PHP_URL_PATH))->assertOk()->assertSee('Garage Moussa')->assertSee("En cours d'examen");
    // L'API JSON reste disponible pour le mobile avec le même jeton.
    $this->getJson('/api/v1/suivi/'.basename($lien))->assertOk()->assertJsonPath('statutCompte', 'en_attente');
});

it('garde la réponse 201 avec jeton_suivi et lien_suivi cohérents', function () {
    configurerSmtp();
    Mail::fake();

    $reponse = depotDossier()->assertCreated()->assertJsonStructure(['jeton_suivi', 'lien_suivi']);

    expect($reponse->json('lien_suivi'))->toEndWith('/suivi/'.$reponse->json('jeton_suivi'));
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

// Mémoire, 3.2.2 : l'e-mail « résultat de l'examen d'un dossier de prestataire » porte le lien de suivi.

/** Chemin « /suivi/… » du lien lu dans la version texte de l'e-mail (null si l'e-mail n'en contient pas). */
function cheminDeSuiviDans(NotificationMail $mail): ?string
{
    $texte = view($mail->content()->text, $mail->content()->with)->render();

    return preg_match('#/suivi/\S+#', $texte, $trouve) === 1 ? $trouve[0] : null;
}

/** Dossier en attente que l'administrateur accepte ou refuse. Renvoie [prestataire, chemin du lien de suivi de l'e-mail de résultat]. */
function resultatExamenDossier(string $action, array $corps = []): array
{
    configurerSmtp();
    Mail::fake();
    $prestataire = Prestataire::factory()
        ->for(User::factory()->prestataire()->statut(StatutCompte::EnAttenteValidation)->state(['prenom' => 'Moussa', 'email' => 'moussa@exemple.test']), 'user')
        ->create(['nom_entreprise' => 'Garage Moussa']);

    test()->actingAs(User::factory()->administrateur()->create(), 'sanctum')
        ->postJson("/api/v1/admin/prestataires/{$prestataire->id}/$action", $corps)->assertOk();

    $chemin = null;
    Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use (&$chemin) {
        $chemin = cheminDeSuiviDans($mail);

        return $mail->hasTo('moussa@exemple.test');
    });

    return [$prestataire, $chemin];
}

it('joint le lien de suivi à l\'e-mail du résultat de l\'examen, dossier validé comme refusé', function (string $action, array $corps, string $statut, string $titre, string $detail) {
    [$prestataire, $chemin] = resultatExamenDossier($action, $corps);

    expect($chemin)->not->toBeNull()->toStartWith('/suivi/'.$prestataire->id.'.');
    $this->get($chemin)->assertOk()->assertSee('Garage Moussa')->assertSee($titre)->assertSee($detail);
    $this->getJson('/api/v1'.$chemin)->assertOk()->assertJsonPath('statutCompte', $statut)->assertJsonPath('nomEntreprise', 'Garage Moussa');

    // Comme au dépôt : le lien n'est conservé ni en clair ni chiffré une fois l'e-mail parti.
    $notification = Notification::sole();
    expect($notification->canal_email_statut)->toBe(StatutEnvoi::Envoye)
        ->and($notification->secret_chiffre)->toBeNull()
        ->and(contenuDeLaBase())->not->toContain(explode('.', $chemin)[1]);
})->with([
    'dossier validé' => ['accepter', [], 'actif', 'Dossier accepté, vous pouvez vous connecter', 'Votre compte prestataire est actif'],
    'dossier refusé' => ['refuser', ['motif' => 'La photocopie de la CNI est illisible.'], 'refuse', 'Dossier refusé', 'La photocopie de la CNI est illisible.'],
]);

it('garde valide le jeton remis à l\'inscription, à côté du lien de l\'e-mail de résultat', function () {
    configurerSmtp();
    Mail::fake();
    $jeton = depotDossier()->assertCreated()->json('jeton_suivi');

    $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
        ->postJson('/api/v1/admin/prestataires/'.Prestataire::sole()->id.'/accepter')->assertOk();

    $chemins = [];
    Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use (&$chemins) {
        $chemins[$mail->notification->type->value] = cheminDeSuiviDans($mail);

        return true;
    });
    expect($chemins)->toHaveCount(2)
        ->and($chemins['dossier_depose'])->toBe('/suivi/'.$jeton)
        ->and($chemins['demande_acceptee'])->not->toBeNull()->not->toBe($chemins['dossier_depose']);

    // Le mobile garde le jeton de l'inscription : les deux liens ouvrent le même suivi.
    foreach ($chemins as $chemin) {
        $this->getJson('/api/v1'.$chemin)->assertOk()->assertJsonPath('statutCompte', 'actif');
    }
});

it('ne reconnaît le jeton signé que pour son dossier et avec la clé de l\'application', function () {
    $dossier = Prestataire::factory()->refuse('La photocopie de la CNI est illisible.')->create(['nom_entreprise' => 'Garage Moussa']);
    $autre = Prestataire::factory()->create();
    $service = app(SuiviDossierService::class);
    $jeton = $service->jetonDuResultat($dossier);
    [, $signature] = explode('.', $jeton);

    expect($service->jetonDuResultat($dossier))->toBe($jeton)
        ->and($service->trouverParJeton($jeton)?->id)->toBe($dossier->id);
    // Sans authentification, comme le lien reçu par e-mail.
    $this->get('/suivi/'.$jeton)->assertOk()->assertSee('Dossier refusé')->assertSee('La photocopie de la CNI est illisible.');

    foreach ([
        $autre->id.'.'.$signature,                // signature d'un autre dossier
        $dossier->id.'.'.strrev($signature),      // signature altérée
        $dossier->id.'.',                         // signature vide
        $jeton.'0',                               // signature rallongée
        $dossier->id,                             // identifiant seul
        'pas-un-identifiant.'.$signature,         // identifiant qui n'est pas un UUID
    ] as $faux) {
        $this->getJson('/api/v1/suivi/'.$faux)->assertNotFound()->assertExactJson(['message' => 'Lien de suivi invalide ou expiré.']);
        expect($service->trouverParJeton($faux))->toBeNull();
    }

    // Une autre clé d'application, ou une clé vide, ne valide plus rien et ne permet pas de signer.
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    expect($service->trouverParJeton($jeton))->toBeNull();
    config(['app.key' => '']);
    expect($service->trouverParJeton($jeton))->toBeNull()
        ->and(fn () => $service->jetonDuResultat($dossier))->toThrow(RuntimeException::class, 'APP_KEY est vide');
});

it('refuse de composer l\'e-mail de résultat sans lien lisible', function (TypeNotification $type) {
    $user = User::factory()->prestataire()->create(['email' => 'moussa@exemple.test']);
    $notification = app(NotificationService::class)->creer($user, $type, 'Dossier examiné', 'Texte', [], [Canal::Email]);

    expect(fn () => (new NotificationMail($notification, $user))->render())->toThrow(RuntimeException::class, 'Lien de suivi indisponible');
})->with([TypeNotification::DemandeAcceptee, TypeNotification::DemandeRefusee]);
