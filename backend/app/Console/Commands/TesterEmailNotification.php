<?php

namespace App\Console\Commands;

use App\Services\Notification\MasqueErreur;
use App\Services\Notification\SmtpEnvoiEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/** Envoie un e-mail de test (sans donnée réelle) pour vérifier les variables MAIL_*. À lancer à la main (tâche Dokploy). N'affiche aucun secret. */
class TesterEmailNotification extends Command
{
    protected $signature = 'sopi:notifications:tester-email {adresse : Adresse qui reçoit le message de test}';

    protected $description = 'Envoie un e-mail de test via la configuration SMTP actuelle';

    public function handle(): int
    {
        $adresse = (string) $this->argument('adresse');
        if (Validator::make(['a' => $adresse], ['a' => 'required|email'])->fails()) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }

        try {
            SmtpEnvoiEmail::verifierConfiguration();
            Mail::raw("Bonjour,\n\nCeci est un message de test de Sopi. Si vous le lisez, l'envoi d'e-mails fonctionne.\n\nL'équipe Sopi", function ($message) use ($adresse) {
                $message->to($adresse)->subject('Sopi : e-mail de test');
            });
        } catch (Throwable $e) {
            $this->error('Échec de l\'envoi : '.MasqueErreur::message($e));

            return self::FAILURE;
        }

        $this->info('E-mail de test envoyé. Vérifiez la boîte de réception (et les indésirables).');

        return self::SUCCESS;
    }
}
