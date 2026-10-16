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
