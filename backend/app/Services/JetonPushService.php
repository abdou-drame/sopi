<?php

namespace App\Services;

use App\Enums\StatutEnvoi;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Jeton push (FCM) de l'appareil d'un utilisateur (M10.3). Un jeton désigne un appareil : il ne peut appartenir qu'à un compte
 * à la fois. Si un autre compte se connecte sur le même appareil, la dernière affectation l'emporte et l'ancien propriétaire
 * perd le jeton (il ne recevra plus les push de l'autre personne). Le jeton n'est jamais renvoyé par l'API.
 */
class JetonPushService
{
    public function enregistrer(User $utilisateur, string $jeton): void
    {
        DB::transaction(function () use ($utilisateur, $jeton) {
            $anciens = User::query()->where('jeton_push', $jeton)->where('id', '!=', $utilisateur->id)->pluck('id');
            if ($anciens->isNotEmpty()) {
                User::query()->whereIn('id', $anciens)->update(['jeton_push' => null]);
                $this->abandonnerPushEnAttente($anciens->all());
            }

            $utilisateur->forceFill(['jeton_push' => $jeton])->save();
        });
    }

    /** Retire le jeton (déconnexion de l'appareil, notifications push désactivées). Sans effet si aucun jeton n'est enregistré. */
    public function retirer(User $utilisateur): void
    {
        DB::transaction(function () use ($utilisateur) {
            $utilisateur->forceFill(['jeton_push' => null])->save();
            $this->abandonnerPushEnAttente([$utilisateur->id]);
        });
    }

    /** Sans jeton, les push encore en attente n'ont plus de destination : ils sont abandonnés (l'in-app et l'e-mail restent). */
    private function abandonnerPushEnAttente(array $idsUtilisateurs): void
    {
        Notification::query()
            ->whereIn('user_id', $idsUtilisateurs)
            ->where('canal_push_statut', StatutEnvoi::EnAttente->value)
            ->update(['canal_push_statut' => StatutEnvoi::NonApplicable->value]);
    }
}
