<?php

use App\Enums\Canal;
use App\Enums\StatutCompte;
use App\Enums\StatutEnvoi;
use App\Enums\TypeNotification;
use App\Exceptions\ConfigurationNotificationInvalide;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\EnvoiEmail;
use App\Services\Notification\EnvoiPush;
use App\Services\Notification\LogEnvoiEmail;
use App\Services\Notification\LogEnvoiPush;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Pilote d'essai : compte les envois et peut échouer. */
function pilotesEnvoi(bool $emailEchoue = false, bool $pushEchoue = false): object
{
    $etat = new stdClass;
    $etat->emails = 0;
    $etat->pushs = 0;

    app()->bind(EnvoiEmail::class, fn () => new class($etat, $emailEchoue) implements EnvoiEmail
    {
        public function __construct(private object $etat, private bool $echoue) {}

        public function envoyer(Notification $notification, User $destinataire): void
        {
            $this->etat->emails++;
            if ($this->echoue) {
                throw new RuntimeException('secret@example.test +221770000000');
            }
        }
    });
    app()->bind(EnvoiPush::class, fn () => new class($etat, $pushEchoue) implements EnvoiPush
    {
        public function __construct(private object $etat, private bool $echoue) {}

        public function envoyer(Notification $notification, User $destinataire): void
        {
            $this->etat->pushs++;
            if ($this->echoue) {
                throw new RuntimeException('jeton-secret');
            }
        }
    });

    return $etat;
}

function creerNotification(?User $user = null, array $canaux = [Canal::Email, Canal::Push]): ?Notification
{
    $user ??= User::factory()->create(['jeton_push' => 'jeton-factice']);

    return app(NotificationService::class)->creer($user, TypeNotification::ReservationConfirmee, 'Réservation confirmée', 'Votre réservation est confirmée.', ['reservationId' => 'abc'], $canaux);
}

beforeEach(function () {
    config(['sopi.notifications.max_tentatives' => 3, 'sopi.notifications.delai_essai_minutes' => 5]);
});

it('crée la notification avec les deux canaux en attente', function () {
    $n = creerNotification();

    expect($n)->not->toBeNull()
        ->and($n->type)->toBe(TypeNotification::ReservationConfirmee)
        ->and($n->canal_email_statut)->toBe(StatutEnvoi::EnAttente)
        ->and($n->canal_push_statut)->toBe(StatutEnvoi::EnAttente)
        ->and($n->lue_le)->toBeNull()
        ->and($n->donnees)->toBe(['reservationId' => 'abc']);
});

it('ignore le push sans jeton et l\'e-mail sans adresse', function () {
    $sansJeton = creerNotification(User::factory()->create());
    $sansEmail = creerNotification(User::factory()->sansEmail()->create(['jeton_push' => 'j']));

    expect($sansJeton->canal_push_statut)->toBe(StatutEnvoi::NonApplicable)
        ->and($sansJeton->canal_email_statut)->toBe(StatutEnvoi::EnAttente)
        ->and($sansEmail->canal_email_statut)->toBe(StatutEnvoi::NonApplicable)
        ->and($sansEmail->canal_push_statut)->toBe(StatutEnvoi::EnAttente);
});

it('respecte les canaux demandés', function () {
    $n = creerNotification(null, [Canal::Push]);

    expect($n->canal_email_statut)->toBe(StatutEnvoi::NonApplicable)
        ->and($n->canal_push_statut)->toBe(StatutEnvoi::EnAttente);
});

it('ne notifie jamais un administrateur', function () {
    $admin = User::factory()->administrateur()->create(['jeton_push' => 'j']);

    expect(creerNotification($admin))->toBeNull()
        ->and(Notification::count())->toBe(0);
});

it('garde l\'in-app mais n\'envoie pas aux comptes suspendus pour les types ordinaires', function () {
    $n = creerNotification(User::factory()->suspendu()->create(['jeton_push' => 'j']));

    expect($n)->not->toBeNull()
        ->and($n->canal_email_statut)->toBe(StatutEnvoi::NonApplicable)
        ->and($n->canal_push_statut)->toBe(StatutEnvoi::NonApplicable);
});

it('envoie quand même les types qui concernent le compte, même suspendu', function () {
    $user = User::factory()->statut(StatutCompte::Suspendu)->create();
    $n = app(NotificationService::class)->creer($user, TypeNotification::SanctionPosee, 'Sanction', 'Un avertissement a été posé.');

    expect($n->canal_email_statut)->toBe(StatutEnvoi::EnAttente);
});

it('envoie les deux canaux avec succès', function () {
    $etat = pilotesEnvoi();
    $n = creerNotification();

    app(NotificationService::class)->envoyer($n);
    $n->refresh();

    expect($etat->emails)->toBe(1)->and($etat->pushs)->toBe(1)
        ->and($n->canal_email_statut)->toBe(StatutEnvoi::Envoye)
        ->and($n->canal_push_statut)->toBe(StatutEnvoi::Envoye)
        ->and($n->tentatives)->toBe(1)
        ->and($n->prochain_essai_le)->toBeNull();
});

it('est idempotent : un second envoi n\'envoie rien de plus', function () {
    $etat = pilotesEnvoi();
    $n = creerNotification();

    app(NotificationService::class)->envoyer($n);
    app(NotificationService::class)->envoyer($n);

    expect($etat->emails)->toBe(1)->and($etat->pushs)->toBe(1);
});

it('ne réessaie pas avant le délai puis réessaie, jusqu\'à l\'abandon', function () {
    $etat = pilotesEnvoi(emailEchoue: true);
    $n = creerNotification();
    $service = app(NotificationService::class);

    $service->envoyer($n);
    $n->refresh();
    expect($n->canal_email_statut)->toBe(StatutEnvoi::EnAttente)
        ->and($n->canal_push_statut)->toBe(StatutEnvoi::Envoye)
        ->and($n->tentatives)->toBe(1)
        ->and($n->dernier_echec_classe)->toBe(RuntimeException::class);

    // Délai non écoulé : aucun nouvel essai.
    $service->envoyer($n);
    expect($etat->emails)->toBe(1);

    $this->travel(6)->minutes();
    $service->envoyer($n);
    expect($etat->emails)->toBe(2)->and($n->fresh()->canal_email_statut)->toBe(StatutEnvoi::EnAttente);

    $this->travel(6)->minutes();
    $service->envoyer($n);
    $n->refresh();
    expect($etat->emails)->toBe(3)
        ->and($n->canal_email_statut)->toBe(StatutEnvoi::Echoue)
        ->and($n->tentatives)->toBe(3)
        ->and($n->prochain_essai_le)->toBeNull();

    // Abandonné : plus aucun essai.
    $this->travel(6)->minutes();
    $service->envoyer($n);
    expect($etat->emails)->toBe(3);
});

it('ne renvoie pas un canal déjà envoyé lors du réessai de l\'autre', function () {
    $etat = pilotesEnvoi(emailEchoue: true);
    $n = creerNotification();

    app(NotificationService::class)->envoyer($n);
    $this->travel(6)->minutes();
    app(NotificationService::class)->envoyer($n);

    expect($etat->pushs)->toBe(1);
});

it('n\'écrit ni adresse, ni jeton, ni texte dans le journal en cas d\'échec', function () {
    pilotesEnvoi(emailEchoue: true, pushEchoue: true);
    $n = creerNotification();
    $messages = [];
    Log::listen(function ($log) use (&$messages) {
        $messages[] = $log->message.' '.json_encode($log->context);
    });

    app(NotificationService::class)->envoyer($n);

    $journal = implode("\n", $messages);
    expect($journal)->toContain($n->id)->toContain(RuntimeException::class)
        ->not->toContain('secret@example.test')
        ->not->toContain('+221770000000')
        ->not->toContain('jeton-secret')
        ->not->toContain('jeton-factice')
        ->not->toContain('Votre réservation')
        ->not->toContain($n->user->email);
});

it('la commande envoie les notifications en attente dues, par lots', function () {
    $etat = pilotesEnvoi();
    $user = User::factory()->create();
    Notification::factory()->enAttente()->count(3)->create(['user_id' => $user->id]);
    Notification::factory()->enAttente()->create(['user_id' => $user->id, 'prochain_essai_le' => now()->addHour()]);
    Notification::factory()->create(['user_id' => $user->id]);
    config(['sopi.notifications.taille_lot' => 2]);

    $this->artisan('sopi:notifications:envoyer-en-attente')->expectsOutput('2 notification(s) traitée(s).')->assertSuccessful();
    expect($etat->emails)->toBe(2);

    $this->artisan('sopi:notifications:envoyer-en-attente')->expectsOutput('1 notification(s) traitée(s).')->assertSuccessful();
    expect($etat->emails)->toBe(3);
});

it('la commande est planifiée chaque minute', function () {
    $evenements = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command, 'sopi:notifications:envoyer-en-attente'));

    expect($evenements)->toHaveCount(1)->and($evenements->first()->expression)->toBe('* * * * *')->and($evenements->first()->withoutOverlapping)->toBeTrue();
});

it('un échec de pilote ne remonte jamais à l\'appelant', function () {
    pilotesEnvoi(emailEchoue: true, pushEchoue: true);
    $n = creerNotification();

    expect(fn () => app(NotificationService::class)->envoyer($n))->not->toThrow(Throwable::class);
});

it('une configuration de pilote invalide n\'empêche rien et se réessaie', function () {
    app()->forgetInstance(EnvoiEmail::class);
    config(['sopi.notifications.email_driver' => 'inconnu']);
    $n = creerNotification(User::factory()->create());

    expect(fn () => app(NotificationService::class)->envoyer($n))->not->toThrow(Throwable::class)
        ->and($n->fresh()->canal_email_statut)->toBe(StatutEnvoi::EnAttente);
});

it('choisit les pilotes selon la configuration', function () {
    expect(app(EnvoiEmail::class))->toBeInstanceOf(LogEnvoiEmail::class)
        ->and(app(EnvoiPush::class))->toBeInstanceOf(LogEnvoiPush::class);

    config(['sopi.notifications.push_driver' => 'fcm']);
    expect(fn () => app(EnvoiPush::class))->toThrow(ConfigurationNotificationInvalide::class, 'pas encore disponible');

    config(['sopi.notifications.email_driver' => 'x']);
    expect(fn () => app(EnvoiEmail::class))->toThrow(ConfigurationNotificationInvalide::class, 'inconnu');
});

it('la base refuse un statut d\'envoi hors liste', function () {
    $n = Notification::factory()->create();

    foreach (['canal_email_statut', 'canal_push_statut'] as $colonne) {
        expect(fn () => DB::table('notifications')->where('id', $n->id)->update([$colonne => 'bizarre']))->toThrow(QueryException::class);
        expect(fn () => DB::table('notifications')->insert([
            'id' => (string) Str::uuid7(), 'user_id' => $n->user_id, 'type' => 'reservation_confirmee', 'titre' => 't', 'corps' => 'c',
            'canal_email_statut' => 'non_applicable', 'canal_push_statut' => 'non_applicable', $colonne => 'bizarre', 'tentatives' => 0,
        ]))->toThrow(QueryException::class);
    }
});

it('la base refuse la suppression d\'un utilisateur qui a des notifications', function () {
    $n = Notification::factory()->create();

    expect(fn () => DB::table('users')->where('id', $n->user_id)->delete())->toThrow(QueryException::class);
});
