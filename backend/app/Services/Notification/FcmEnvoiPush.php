<?php

namespace App\Services\Notification;

use App\Enums\StatutCompte;
use App\Exceptions\ConfigurationNotificationInvalide;
use App\Models\Notification;
use App\Models\User;
use App\Services\AppareilService;
use App\Services\Notification\Fcm\ClientFcm;
use App\Services\Notification\Fcm\FcmErreur;
use App\Services\Notification\Fcm\IdentifiantsFcm;
use Illuminate\Support\Str;

/**
 * Pilote FCM : un push par appareil de l'utilisateur, avec un message court (titre et début du texte, jamais de code de
 * réservation ni de téléphone). Les jetons refusés comme invalides sont supprimés. Si aucun appareil n'a pu être joint pour
 * une raison temporaire (authentification, quota, réseau), l'erreur remonte : la notification reste en attente et sera reprise.
 */
class FcmEnvoiPush implements EnvoiPush
{
    private const LONGUEUR_MAX_CORPS = 120;

    public function __construct(private readonly ClientFcm $client) {}

    /** @throws ConfigurationNotificationInvalide */
    public static function depuisConfiguration(): self
    {
        return new self(new ClientFcm(IdentifiantsFcm::depuisConfiguration()));
    }

    public function envoyer(Notification $notification, User $destinataire): void
    {
        $appareils = app(AppareilService::class);
        $envoyes = 0;
        $echec = null;

        foreach ($appareils->jetons($destinataire) as $jeton) {
            try {
                $resultat = $this->client->envoyer(
                    $jeton,
                    $notification->titre,
                    Str::limit($notification->corps, self::LONGUEUR_MAX_CORPS),
                    ['type' => $notification->type->value, 'notificationId' => $notification->id],
                );
            } catch (FcmErreur $e) {
                $echec = $e;

                continue;
            }

            if ($resultat === 'jeton_invalide') {
                $appareils->supprimerJetonInvalide($jeton);
            } else {
                $envoyes++;
            }
        }

        // Un compte banni ne reçoit plus rien après ce dernier message (la sanction elle-même a pu être notifiée).
        if ($destinataire->statut === StatutCompte::Banni) {
            $appareils->retirerTous($destinataire);
        }

        if ($echec !== null && $envoyes === 0) {
            throw $echec;
        }
    }
}
