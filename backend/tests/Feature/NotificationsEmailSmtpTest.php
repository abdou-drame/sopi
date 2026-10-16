<?php

use App\Enums\StatutEnvoi;
use App\Enums\TypeNotification;
use App\Exceptions\ConfigurationNotificationInvalide;
use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

require_once __DIR__.'/../Helpers/notifications_reelles.php';

function notificationEmail(TypeNotification $type = TypeNotification::ReservationConfirmee, string $corps = 'Votre réservation du 12 mars est confirmée.'): Notification
{
    $user = User::factory()->create(['prenom' => 'Awa', 'email' => 'awa@exemple.test']);
    $notification = new Notification;
    $notification->user_id = $user->id;
    $notification->type = $type;
    $notification->titre = 'Titre de test';
    $notification->corps = $corps;
    $notification->canal_email_statut = StatutEnvoi::EnAttente;
    $notification->canal_push_statut = StatutEnvoi::NonApplicable;
    if ($type->aUnLien()) {
        $notification->definirSecret('https://api.exemple.test/api/v1/suivi/jeton-de-test');
    }
    $notification->save();

    return $notification;
}

it('envoie un e-mail français pour chaque type, sans code de réservation ni numéro', function (TypeNotification $type) {
    configurerSmtp();
    Mail::fake();
    $notification = notificationEmail($type);

    app(NotificationService::class)->envoyer($notification);

    Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($type, $notification) {
        $html = $mail->render();
        expect($mail->hasTo('awa@exemple.test'))->toBeTrue()
            ->and($html)->toContain('Bonjour Awa')->toContain(e($type->accrocheEmail()))->toContain($notification->corps)->toContain('lang="fr"')
            ->and($html)->not->toMatch('/\b[A-Z0-9]{6}\b(?=.*code)/')->not->toMatch('/\+?\d[\d\s.-]{7,}\d/')
            ->and($mail->envelope()->subject)->toBe('Titre de test');

        return true;
    });
    expect($notification->refresh()->canal_email_statut)->toBe(StatutEnvoi::Envoye);
})->with(TypeNotification::cases());

it('envoie aussi une version texte', function () {
    configurerSmtp();
    Mail::fake();
    app(NotificationService::class)->envoyer(notificationEmail());

    Mail::assertSent(NotificationMail::class, fn (NotificationMail $m) => str_contains($m->content()->text, 'texte') && $m->hasTo('awa@exemple.test'));
});

it('laisse la notification en attente avec les NOMS des variables manquantes, jamais les valeurs', function () {
    configurerSmtp(['mail.mailers.smtp.host' => null, 'mail.mailers.smtp.password' => '']);
    Mail::fake();
    $notification = notificationEmail();

    app(NotificationService::class)->envoyer($notification);

    $notification->refresh();
    expect($notification->canal_email_statut)->toBe(StatutEnvoi::EnAttente)
        ->and($notification->tentatives)->toBe(1)
        ->and($notification->dernier_echec_message)->toContain('MAIL_HOST')->toContain('MAIL_PASSWORD')
        ->not->toContain('utilisateur-secret-smtp')
        ->and($notification->dernier_echec_classe)->toBe(ConfigurationNotificationInvalide::class);
    Mail::assertNothingSent();
});

it('exige MAIL_MAILER=smtp quand le pilote smtp est actif', function () {
    configurerSmtp(['mail.default' => 'log']);
    $notification = notificationEmail();

    app(NotificationService::class)->envoyer($notification);

    expect($notification->refresh()->dernier_echec_message)->toContain('MAIL_MAILER=smtp');
});

it('masque adresse, accès et numéro dans l\'erreur du transport, enregistre et compte les essais', function () {
    configurerSmtp();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Connexion refusée pour awa@exemple.test avec utilisateur-secret-smtp / motdepasse-secret-smtp tel +221 77 123 45 67'));
    $notification = notificationEmail();

    app(NotificationService::class)->envoyer($notification);

    $notification->refresh();
    expect($notification->canal_email_statut)->toBe(StatutEnvoi::EnAttente)
        ->and($notification->dernier_echec_message)->toContain('Connexion refusée')
        ->not->toContain('awa@exemple.test')->not->toContain('utilisateur-secret-smtp')->not->toContain('motdepasse-secret-smtp')->not->toContain('77 123')
        ->and(mb_strlen($notification->dernier_echec_message))->toBeLessThanOrEqual(300);
});

it('abandonne définitivement après le maximum d\'essais et expose le message masqué à l\'administrateur', function () {
    configurerSmtp();
    config(['sopi.notifications.max_tentatives' => 2, 'sopi.notifications.delai_essai_minutes' => 0]);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Échec avec awa@exemple.test'));
    $notification = notificationEmail();

    app(NotificationService::class)->envoyer($notification);
    expect($notification->refresh()->canal_email_statut)->toBe(StatutEnvoi::EnAttente);
    $notification->forceFill(['prochain_essai_le' => null])->save();
    app(NotificationService::class)->envoyer($notification);

    expect($notification->refresh()->canal_email_statut)->toBe(StatutEnvoi::Echoue)->and($notification->tentatives)->toBe(2);

    adminConnecte();
    $this->getJson('/api/v1/admin/notifications-en-echec')->assertOk()
        ->assertJsonPath('data.0.messageErreur', fn ($m) => str_contains($m, 'Échec avec') && ! str_contains($m, 'awa@exemple.test'));
});

it('ne journalise ni adresse ni accès SMTP lors d\'un échec', function () {
    configurerSmtp();
    $journal = [];
    Log::listen(function ($message) use (&$journal) {
        $journal[] = $message->message.json_encode($message->context);
    });
    Mail::shouldReceive('to')->andThrow(new RuntimeException('awa@exemple.test motdepasse-secret-smtp'));

    app(NotificationService::class)->envoyer(notificationEmail());

    $tout = implode("\n", $journal);
    expect($tout)->not->toContain('awa@exemple.test')->not->toContain('motdepasse-secret-smtp')->not->toContain('utilisateur-secret-smtp');
});

it('n\'envoie jamais d\'e-mail à un administrateur', function () {
    configurerSmtp();
    Mail::fake();
    $admin = User::factory()->administrateur()->create();

    expect(app(NotificationService::class)->creer($admin, TypeNotification::LitigeOuvert, 'Litige', 'Un litige.'))->toBeNull();
    Mail::assertNothingSent();
});
