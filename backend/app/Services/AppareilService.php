<?php

namespace App\Services;

use App\Enums\PlateformeAppareil;
use App\Enums\StatutEnvoi;
use App\Models\Appareil;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Appareils (jetons FCM) des utilisateurs. Un jeton désigne une installation : il n'appartient qu'à un seul compte, le dernier
 * connecté l'emporte. Le jeton n'est jamais journalisé ni renvoyé.
 */
class AppareilService
{
    /** Enregistre le jeton pour l'utilisateur, ou met à jour sa plateforme et sa dernière activité. */
    public function enregistrer(User $utilisateur, string $jeton, PlateformeAppareil $plateforme): void
    {
        DB::transaction(function () use ($utilisateur, $jeton, $plateforme) {
            $anciens = Appareil::query()->where('jeton', $jeton)->where('user_id', '!=', $utilisateur->id)->pluck('user_id')
                ->merge(User::query()->where('jeton_push', $jeton)->where('id', '!=', $utilisateur->id)->pluck('id'))->unique()->values();
            Appareil::query()->where('jeton', $jeton)->where('user_id', '!=', $utilisateur->id)->delete();
            User::query()->whereIn('id', $anciens)->where('jeton_push', $jeton)->update(['jeton_push' => null]);

            $appareil = Appareil::query()->where('jeton', $jeton)->first() ?? new Appareil;
            $appareil->user_id = $utilisateur->id;
            $appareil->jeton = $jeton;
            $appareil->plateforme = $plateforme;
            $appareil->derniere_activite = now();
            $appareil->save();

            $this->abandonnerPushSansAppareil($anciens->all());
        });
    }

    /** Retire un jeton de l'utilisateur (déconnexion). Vrai s'il existait. */
    public function retirer(User $utilisateur, string $jeton): bool
    {
        return DB::transaction(function () use ($utilisateur, $jeton) {
            $supprimes = Appareil::query()->where('user_id', $utilisateur->id)->where('jeton', $jeton)->delete();
            User::query()->whereKey($utilisateur->id)->where('jeton_push', $jeton)->update(['jeton_push' => null]);
            $this->abandonnerPushSansAppareil([$utilisateur->id]);

            return $supprimes > 0;
        });
    }

    /** Retire tous les jetons d'un utilisateur (compte banni ou supprimé). */
    public function retirerTous(User $utilisateur): void
    {
        DB::transaction(function () use ($utilisateur) {
            Appareil::query()->where('user_id', $utilisateur->id)->delete();
            User::query()->whereKey($utilisateur->id)->update(['jeton_push' => null]);
            $this->abandonnerPushSansAppareil([$utilisateur->id]);
        });
    }

    /** Supprime un jeton refusé par FCM, quel que soit son propriétaire. */
    public function supprimerJetonInvalide(string $jeton): void
    {
        Appareil::query()->where('jeton', $jeton)->delete();
        User::query()->where('jeton_push', $jeton)->update(['jeton_push' => null]);
    }

    /** @return list<string> jetons de l'utilisateur (appareils, plus l'ancien jeton unique s'il existe encore) */
    public function jetons(User $utilisateur): array
    {
        $jetons = Appareil::query()->where('user_id', $utilisateur->id)->pluck('jeton')->all();
        if (filled($utilisateur->jeton_push)) {
            $jetons[] = $utilisateur->jeton_push;
        }

        return array_values(array_unique($jetons));
    }

    public function aDesJetons(User $utilisateur): bool
    {
        return filled($utilisateur->jeton_push) || Appareil::query()->where('user_id', $utilisateur->id)->exists();
    }

    /** Sans appareil, les push encore en attente n'ont plus de destination : ils sont abandonnés (l'in-app et l'e-mail restent). */
    private function abandonnerPushSansAppareil(array $idsUtilisateurs): void
    {
        $sansAppareil = User::query()->whereIn('id', $idsUtilisateurs)
            ->whereNull('jeton_push')
            ->whereDoesntHave('appareils')
            ->pluck('id');

        Notification::query()
            ->whereIn('user_id', $sansAppareil)
            ->where('canal_push_statut', StatutEnvoi::EnAttente->value)
            ->update(['canal_push_statut' => StatutEnvoi::NonApplicable->value]);
    }
}
