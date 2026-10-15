<?php

namespace App\Services\Notification;

use App\Enums\Canal;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Enums\StatutEnvoi;
use App\Enums\TypeNotification;
use App\Models\Notification;
use App\Models\User;
use App\Services\AppareilService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LaravelNotificationService implements NotificationService
{
    public function creer(User $utilisateur, TypeNotification $type, string $titre, string $corps, array $donnees = [], array $canaux = [Canal::Email, Canal::Push], ?string $secret = null): ?Notification
    {
        if ($utilisateur->role === Role::Administrateur) {
            return null;
        }

        $envoiPossible = $type->ignoreStatutDuCompte() || $utilisateur->statut === StatutCompte::Actif;
        $email = $envoiPossible && in_array(Canal::Email, $canaux, true) && filled($utilisateur->email);
        $push = $envoiPossible && in_array(Canal::Push, $canaux, true) && app(AppareilService::class)->aDesJetons($utilisateur);

        $notification = new Notification;
        $notification->user_id = $utilisateur->id;
        $notification->type = $type;
        $notification->titre = $titre;
        $notification->corps = $corps;
        $notification->donnees = $donnees === [] ? null : $donnees;
        $notification->canal_email_statut = $email ? StatutEnvoi::EnAttente : StatutEnvoi::NonApplicable;
        $notification->canal_push_statut = $push ? StatutEnvoi::EnAttente : StatutEnvoi::NonApplicable;
        $notification->definirSecret($email ? $secret : null);
        $notification->save();

        if ($email || $push) {
            // Premier essai après l'envoi de la réponse : l'action métier n'attend jamais le serveur d'e-mail ni FCM.
            // En cas d'échec ou d'arrêt avant, la commande planifiée reprend la ligne.
            $id = $notification->id;
            defer(fn () => $this->envoyerParId($id), always: true);
        }

        return $notification;
    }

    public function envoyer(Notification $notification): void
    {
        $this->envoyerParId($notification->id);
    }

    public function envoyerEnAttente(int $limite): int
    {
        $ids = Notification::query()
            ->where(fn ($q) => $q->where('canal_email_statut', StatutEnvoi::EnAttente->value)->orWhere('canal_push_statut', StatutEnvoi::EnAttente->value))
            ->where(fn ($q) => $q->whereNull('prochain_essai_le')->orWhere('prochain_essai_le', '<=', now()))
            ->orderBy('created_at')
            ->limit($limite)
            ->pluck('id');

        foreach ($ids as $id) {
            $this->envoyerParId($id);
        }

        return $ids->count();
    }

    private function envoyerParId(string $id): void
    {
        try {
            $this->traiter($id);
        } catch (Throwable $e) {
            Log::warning('Envoi de notification interrompu.', ['notification' => $id, 'erreur' => $e::class]);
        }
    }

    private function traiter(string $id): void
    {
        // Réservation de la ligne par UPDATE conditionnel : un seul traitement à la fois, et un essai compté par passage.
        // Le délai posé ici sert aussi de reprise si le traitement s'arrête avant d'avoir fini.
        $pris = Notification::query()
            ->whereKey($id)
            ->where(fn ($q) => $q->where('canal_email_statut', StatutEnvoi::EnAttente->value)->orWhere('canal_push_statut', StatutEnvoi::EnAttente->value))
            ->where(fn ($q) => $q->whereNull('prochain_essai_le')->orWhere('prochain_essai_le', '<=', now()))
            ->update([
                'tentatives' => DB::raw('tentatives + 1'),
                'prochain_essai_le' => now()->addMinutes((int) config('sopi.notifications.delai_essai_minutes')),
            ]);
        if ($pris !== 1) {
            return;
        }

        $notification = Notification::query()->with('user')->findOrFail($id);
        $echec = null;
        $message = null;

        foreach ([Canal::Email, Canal::Push] as $canal) {
            $colonne = "canal_{$canal->value}_statut";
            if ($notification->{$colonne} !== StatutEnvoi::EnAttente) {
                continue;
            }

            try {
                match ($canal) {
                    Canal::Email => app(EnvoiEmail::class)->envoyer($notification, $notification->user),
                    Canal::Push => app(EnvoiPush::class)->envoyer($notification, $notification->user),
                };
                $this->changerStatut($id, $colonne, StatutEnvoi::Envoye);
            } catch (Throwable $e) {
                $echec = $e::class;
                $message = MasqueErreur::message($e);
                // Seul l'identifiant et la classe de l'erreur sont journalisés : jamais d'adresse, de jeton ni de texte.
                Log::warning('Échec d\'envoi de notification.', ['notification' => $id, 'canal' => $canal->value, 'erreur' => $echec]);
            }
        }

        $this->clore($id, (int) $notification->tentatives, $echec, $message);
    }

    /** Après un passage : abandonne les canaux restants au maximum d'essais, libère la ligne quand il n'y a plus rien à envoyer. */
    private function clore(string $id, int $tentativesLues, ?string $echec, ?string $message = null): void
    {
        // $tentativesLues vient de la relecture faite après la réservation de la ligne : elle inclut déjà cet essai.
        $maximum = (int) config('sopi.notifications.max_tentatives');

        if ($echec !== null) {
            Notification::query()->whereKey($id)->update(['dernier_echec_classe' => mb_substr($echec, 0, 150), 'dernier_echec_message' => $message]);
        }

        if ($echec !== null && $tentativesLues >= $maximum) {
            foreach (['canal_email_statut', 'canal_push_statut'] as $colonne) {
                $this->changerStatut($id, $colonne, StatutEnvoi::Echoue);
            }
        }

        // Plus rien à envoyer : on libère la ligne et on efface le secret chiffré (envoi réussi ou échec définitif).
        Notification::query()->whereKey($id)
            ->where('canal_email_statut', '!=', StatutEnvoi::EnAttente->value)
            ->where('canal_push_statut', '!=', StatutEnvoi::EnAttente->value)
            ->update(['prochain_essai_le' => null, 'secret_chiffre' => null]);
    }

    /** Ne change que ce qui est encore en attente : un envoi déjà constaté n'est jamais écrasé. */
    private function changerStatut(string $id, string $colonne, StatutEnvoi $statut): void
    {
        Notification::query()->whereKey($id)->where($colonne, StatutEnvoi::EnAttente->value)->update([$colonne => $statut->value]);
    }
}
