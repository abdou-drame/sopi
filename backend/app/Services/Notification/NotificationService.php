<?php

namespace App\Services\Notification;

use App\Enums\Canal;
use App\Enums\TypeNotification;
use App\Models\Notification;
use App\Models\User;

/** Notifications des utilisateurs : enregistrement dans l'application et envoi par e-mail et push (isole les pilotes du reste du système). */
interface NotificationService
{
    /**
     * Enregistre la notification et prépare l'envoi sur les canaux demandés. Retourne null pour un administrateur, qui ne reçoit
     * ni notification ni e-mail ni push (il est alerté par des bannières sur son tableau de bord).
     *
     * @param  array<string, mixed>  $donnees  identifiants utiles à l'application, jamais de donnée sensible
     * @param  list<Canal>  $canaux
     */
    public function creer(User $utilisateur, TypeNotification $type, string $titre, string $corps, array $donnees = [], array $canaux = [Canal::Email, Canal::Push]): ?Notification;

    /**
     * Tente l'envoi des canaux en attente. Sans effet si un autre traitement a déjà pris la ligne, si l'essai n'est pas encore
     * dû ou si rien n'est en attente : une notification n'est jamais envoyée deux fois en parallèle. Ne lève jamais d'exception.
     */
    public function envoyer(Notification $notification): void;

    /** Envoie par lots les notifications en attente dont l'essai est dû. Retourne le nombre de lignes traitées. */
    public function envoyerEnAttente(int $limite): int;
}
