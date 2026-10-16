<?php

namespace App\Services;

use App\Enums\Canal;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Enums\TypeNotification;
use App\Models\ReinitialisationMotDePasse;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Réinitialisation du mot de passe par e-mail. Seul le hachage SHA-256 du jeton est en base ; le jeton en clair n'existe que dans
 * le lien transmis (donnée chiffrée de la notification, effacée après l'envoi) : il n'est ni journalisé ni renvoyé par l'API.
 */
class ReinitialisationMotDePasseService
{
    public const MESSAGE_DEMANDE = 'Si un compte correspond à cette adresse, un lien de réinitialisation vient d\'être envoyé.';

    /** Page web où le lien envoyé par e-mail s'ouvre. */
    public static function lien(string $jeton): string
    {
        return rtrim(config('app.url'), '/').'/reinitialiser-mot-de-passe/'.$jeton;
    }

    /** Seul un compte actif, non administrateur et doté d'une adresse e-mail peut réinitialiser son mot de passe. */
    public function estEligible(?User $user): bool
    {
        return $user !== null
            && $user->role !== Role::Administrateur
            && $user->statut === StatutCompte::Actif
            && filled($user->email);
    }

    /**
     * Traite une demande. Ne révèle rien : le résultat est le même que l'adresse existe, soit éligible ou non, et la durée est
     * portée à un minimum fixe pour que le temps de réponse ne trahisse pas l'existence du compte.
     */
    public function demander(string $email): void
    {
        $debut = hrtime(true);

        try {
            $user = User::query()->where('email', $email)->first();
            if ($this->estEligible($user)) {
                $this->envoyerLien($user);
            }
        } catch (Throwable $erreur) {
            // Jamais d'adresse ni de jeton dans les journaux : seulement la classe de l'erreur.
            Log::warning('Demande de réinitialisation de mot de passe non traitée.', ['erreur' => $erreur::class]);
        }

        $reste = (int) config('securite.reinitialisation.duree_minimale_ms') - (int) ((hrtime(true) - $debut) / 1_000_000);
        if ($reste > 0) {
            usleep($reste * 1000);
        }
    }

    /** Jeton encore utilisable (connu, non utilisé, non expiré) dont le compte reste éligible. Comparaison du hachage à temps constant. */
    public function trouverValide(string $jeton): ?ReinitialisationMotDePasse
    {
        $hash = hash('sha256', $jeton);
        $ligne = ReinitialisationMotDePasse::query()->with('user')->where('jeton_hash', $hash)->first();

        if ($ligne === null || ! hash_equals($ligne->jeton_hash, $hash) || $ligne->utilise_le !== null || $ligne->expire_le->isPast()) {
            return null;
        }

        return $this->estEligible($ligne->user) ? $ligne : null;
    }

    /**
     * Change le mot de passe avec un jeton valide. Usage unique (réservation par mise à jour conditionnelle, sûre en cas de requêtes
     * simultanées), tous les jetons d'accès de l'utilisateur sont révoqués, un e-mail de confirmation (sans lien) est envoyé.
     * Renvoie false si le jeton est inconnu, expiré, déjà utilisé ou si le compte n'est plus éligible.
     */
    public function reinitialiser(string $jeton, string $motDePasse): bool
    {
        $ligne = $this->trouverValide($jeton);
        if ($ligne === null) {
            return false;
        }

        $user = DB::transaction(function () use ($ligne, $motDePasse) {
            $pris = ReinitialisationMotDePasse::query()->whereKey($ligne->id)->whereNull('utilise_le')->update(['utilise_le' => now()]);
            if ($pris !== 1) {
                return null;
            }

            $user = $ligne->user;
            $user->password = $motDePasse;
            $user->save();
            ReinitialisationMotDePasse::query()->where('user_id', $user->id)->whereNull('utilise_le')->update(['utilise_le' => now()]);
            $user->tokens()->delete();

            return $user;
        });
        if ($user === null) {
            return false;
        }

        $this->notifierChangement($user);

        return true;
    }

    /** Une panne de la notification ne doit jamais annuler le changement de mot de passe. */
    private function notifierChangement(User $user): void
    {
        try {
            app(NotificationService::class)->creer(
                $user,
                TypeNotification::MotDePasseModifie,
                'Mot de passe modifié',
                'Le mot de passe de votre compte Sopi vient d\'être modifié et vous avez été déconnecté de vos appareils. Si vous n\'êtes pas à l\'origine de ce changement, réinitialisez-le immédiatement avec « Mot de passe oublié ».',
                [],
                [Canal::Email],
            );
        } catch (Throwable $erreur) {
            Log::warning('Notification de changement de mot de passe non créée.', ['erreur' => $erreur::class]);
        }
    }

    private function envoyerLien(User $user): void
    {
        $jeton = bin2hex(random_bytes(32));

        // Un seul jeton valable par compte : les précédents sont invalidés.
        DB::transaction(function () use ($user, $jeton) {
            ReinitialisationMotDePasse::query()->where('user_id', $user->id)->whereNull('utilise_le')->update(['utilise_le' => now()]);

            $ligne = new ReinitialisationMotDePasse;
            $ligne->user_id = $user->id;
            $ligne->jeton_hash = hash('sha256', $jeton);
            $ligne->expire_le = now()->addMinutes((int) config('securite.reinitialisation.duree_minutes'));
            $ligne->save();
        });

        app(NotificationService::class)->creer(
            $user,
            TypeNotification::ReinitialisationMotDePasse,
            'Réinitialisation de votre mot de passe',
            'Une demande de réinitialisation de mot de passe a été faite pour votre compte Sopi. Utilisez le lien ci-dessous pour choisir un nouveau mot de passe.',
            [],
            [Canal::Email],
            self::lien($jeton),
        );
    }
}
