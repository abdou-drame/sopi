<?php

namespace App\Providers;

use App\Events\AvisDepose;
use App\Events\AvisModere;
use App\Events\DemandePrestataireAcceptee;
use App\Events\DemandePrestataireRefusee;
use App\Events\LitigeCloture;
use App\Events\LitigeOuvert;
use App\Events\Reservation\ReservationAcceptee;
use App\Events\Reservation\ReservationAnnulee;
use App\Events\Reservation\ReservationConfirmee;
use App\Events\Reservation\ReservationCreee;
use App\Events\Reservation\ReservationDemarree;
use App\Events\Reservation\ReservationExpiree;
use App\Events\Reservation\ReservationRefusee;
use App\Events\Reservation\ReservationTerminee;
use App\Events\SanctionLevee;
use App\Events\SanctionPosee;
use App\Exceptions\ConfigurationNotificationInvalide;
use App\Exceptions\ConfigurationPaiementInvalide;
use App\Listeners\NotifierLesUtilisateurs;
use App\Services\Notification\EnvoiEmail;
use App\Services\Notification\EnvoiPush;
use App\Services\Notification\LaravelNotificationService;
use App\Services\Notification\LogEnvoiEmail;
use App\Services\Notification\LogEnvoiPush;
use App\Services\Notification\NotificationService;
use App\Services\Notification\SmtpEnvoiEmail;
use App\Services\Paiement\CalculPaiement;
use App\Services\Paiement\FakePaiementService;
use App\Services\Paiement\PaiementService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Pilote de paiement choisi par PAYMENT_DRIVER (fake ou dexpay). DexPay arrive en M6.6.
        $this->app->singleton(PaiementService::class, fn () => match ($pilote = (string) config('sopi.paiement.driver')) {
            'fake' => new FakePaiementService(config('sopi.paiement.webhook_secret')),
            'dexpay' => throw new ConfigurationPaiementInvalide('Le prestataire de paiement DexPay n\'est pas encore disponible.'),
            default => throw new ConfigurationPaiementInvalide("Le pilote de paiement « {$pilote} » est inconnu (valeurs possibles : fake, dexpay)."),
        });

        $this->app->bind(CalculPaiement::class, fn () => CalculPaiement::depuisConfiguration());

        // Notifications (M10) : pilotes choisis par NOTIFICATION_EMAIL_DRIVER et NOTIFICATION_PUSH_DRIVER.
        $this->app->bind(NotificationService::class, LaravelNotificationService::class);
        $this->app->bind(EnvoiEmail::class, fn () => match ($pilote = (string) config('sopi.notifications.email_driver')) {
            'log' => new LogEnvoiEmail,
            'smtp' => new SmtpEnvoiEmail,
            default => throw new ConfigurationNotificationInvalide("Le pilote d'e-mail « {$pilote} » est inconnu (valeurs possibles : log, smtp)."),
        });
        $this->app->bind(EnvoiPush::class, fn () => match ($pilote = (string) config('sopi.notifications.push_driver')) {
            'log' => new LogEnvoiPush,
            'fcm' => throw new ConfigurationNotificationInvalide('Le service de notifications push FCM n\'est pas encore disponible.'),
            default => throw new ConfigurationNotificationInvalide("Le pilote push « {$pilote} » est inconnu (valeurs possibles : log, fcm)."),
        });
    }

    public function boot(): void
    {
        // Notifications (M10.2) : un écouteur par événement métier, enregistrés explicitement (pas de découverte automatique).
        Event::listen(AvisDepose::class, [NotifierLesUtilisateurs::class, 'surAvisDepose']);
        Event::listen(AvisModere::class, [NotifierLesUtilisateurs::class, 'surAvisModere']);
        Event::listen(DemandePrestataireAcceptee::class, [NotifierLesUtilisateurs::class, 'surDemandePrestataireAcceptee']);
        Event::listen(DemandePrestataireRefusee::class, [NotifierLesUtilisateurs::class, 'surDemandePrestataireRefusee']);
        Event::listen(LitigeCloture::class, [NotifierLesUtilisateurs::class, 'surLitigeCloture']);
        Event::listen(LitigeOuvert::class, [NotifierLesUtilisateurs::class, 'surLitigeOuvert']);
        Event::listen(ReservationAcceptee::class, [NotifierLesUtilisateurs::class, 'surReservationAcceptee']);
        Event::listen(ReservationAnnulee::class, [NotifierLesUtilisateurs::class, 'surReservationAnnulee']);
        Event::listen(ReservationConfirmee::class, [NotifierLesUtilisateurs::class, 'surReservationConfirmee']);
        Event::listen(ReservationCreee::class, [NotifierLesUtilisateurs::class, 'surReservationCreee']);
        Event::listen(ReservationDemarree::class, [NotifierLesUtilisateurs::class, 'surReservationDemarree']);
        Event::listen(ReservationExpiree::class, [NotifierLesUtilisateurs::class, 'surReservationExpiree']);
        Event::listen(ReservationRefusee::class, [NotifierLesUtilisateurs::class, 'surReservationRefusee']);
        Event::listen(ReservationTerminee::class, [NotifierLesUtilisateurs::class, 'surReservationTerminee']);
        Event::listen(SanctionLevee::class, [NotifierLesUtilisateurs::class, 'surSanctionLevee']);
        Event::listen(SanctionPosee::class, [NotifierLesUtilisateurs::class, 'surSanctionPosee']);

        // 5 inscriptions par minute et par adresse IP.
        RateLimiter::for('inscription', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('inscription-prestataire', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('suivi-dossier', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Profil : limites par utilisateur connecté (par IP en secours). Le changement de mot de passe
        // est le plus strict (protège l'ancien mot de passe contre les essais répétés avec un jeton volé).
        $parUtilisateur = fn (Request $request) => $request->user()?->id ?? $request->ip();
        RateLimiter::for('mot-de-passe', fn (Request $request) => Limit::perMinute(5)->by($parUtilisateur($request)));
        RateLimiter::for('profil', fn (Request $request) => Limit::perMinute(30)->by($parUtilisateur($request)));
        RateLimiter::for('verrou-creneau', fn (Request $request) => Limit::perMinute(20)->by($parUtilisateur($request)));
        RateLimiter::for('reservation', fn (Request $request) => Limit::perMinute(20)->by($parUtilisateur($request)));
        RateLimiter::for('reponse-reservation', fn (Request $request) => Limit::perMinute(30)->by($parUtilisateur($request)));
        RateLimiter::for('annulation-reservation', fn (Request $request) => Limit::perMinute(30)->by($parUtilisateur($request)));
        // Anti-devinette du code de réservation : 5 essais par minute, par utilisateur ET par réservation (clé composée).
        RateLimiter::for('code-reservation', fn (Request $request) => Limit::perMinute(5)->by($parUtilisateur($request).'|'.$request->route('id')));
        RateLimiter::for('paiement-reservation', fn (Request $request) => Limit::perMinute(10)->by($parUtilisateur($request)));
        // Webhook du prestataire de paiement : public, limité par adresse IP.
        RateLimiter::for('webhook-paiement', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        // Retrait : opération financière, 5 demandes par minute et par utilisateur.
        RateLimiter::for('retrait', fn (Request $request) => Limit::perMinute(5)->by($parUtilisateur($request)));
        // Dépôt d'un avis : 10 par minute et par utilisateur.
        RateLimiter::for('avis', fn (Request $request) => Limit::perMinute(10)->by($parUtilisateur($request)));
        // Poser ou lever une sanction : 10 par minute et par administrateur.
        RateLimiter::for('sanction', fn (Request $request) => Limit::perMinute(10)->by($parUtilisateur($request)));
        // Ouverture d'un litige par le client, réponse du prestataire et décision de l'administrateur.
        RateLimiter::for('litige', fn (Request $request) => Limit::perMinute(5)->by($parUtilisateur($request)));
        RateLimiter::for('jeton-push', fn (Request $request) => Limit::perMinute(30)->by($parUtilisateur($request)));

        // Anti-balayage par adresse IP. Le blocage par numéro (5 échecs => 15 min) est géré
        // par BlocageConnexionService.
        RateLimiter::for('connexion', fn (Request $request) => Limit::perMinute(config('securite.connexion.max_par_minute_par_ip'))
            ->by($request->ip()));
    }
}
