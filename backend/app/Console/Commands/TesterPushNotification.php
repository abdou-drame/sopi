<?php

namespace App\Console\Commands;

use App\Services\Notification\Fcm\ClientFcm;
use App\Services\Notification\Fcm\IdentifiantsFcm;
use App\Services\Notification\MasqueErreur;
use Illuminate\Console\Command;
use Throwable;

/** Envoie un push de test à un jeton d'appareil (sans donnée réelle) pour vérifier FCM_CREDENTIALS. À lancer à la main. N'affiche ni le jeton ni aucun secret. */
class TesterPushNotification extends Command
{
    protected $signature = 'sopi:notifications:tester-push {jeton : Jeton FCM de l\'appareil de test}';

    protected $description = 'Envoie un push de test via la configuration FCM actuelle';

    public function handle(): int
    {
        $jeton = trim((string) $this->argument('jeton'));
        if ($jeton === '') {
            $this->error('Jeton vide.');

            return self::FAILURE;
        }

        try {
            $resultat = (new ClientFcm(IdentifiantsFcm::depuisConfiguration()))
                ->envoyer($jeton, 'Sopi : test', 'Ceci est une notification de test.', ['type' => 'test']);
        } catch (Throwable $e) {
            $this->error('Échec de l\'envoi : '.MasqueErreur::message($e));

            return self::FAILURE;
        }

        if ($resultat === 'jeton_invalide') {
            $this->error('Authentification Google correcte, mais FCM refuse ce jeton (expiré ou invalide).');

            return self::FAILURE;
        }

        $this->info('Push de test envoyé.');

        return self::SUCCESS;
    }
}
