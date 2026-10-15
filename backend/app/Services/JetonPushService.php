<?php

namespace App\Services;

use App\Enums\PlateformeAppareil;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Jeton push (FCM) de l'appareil d'un utilisateur (M10.3). Un jeton désigne un appareil : il ne peut appartenir qu'à un compte
 * à la fois. Si un autre compte se connecte sur le même appareil, la dernière affectation l'emporte et l'ancien propriétaire
 * perd le jeton (il ne recevra plus les push de l'autre personne). Le jeton n'est jamais renvoyé par l'API.
 */
class JetonPushService
{
    public function __construct(private readonly AppareilService $appareils) {}

    /** Ancienne route PUT /me/jeton-push : la plateforme n'est pas connue, android par défaut. Les clients récents utilisent POST /appareils. */
    public function enregistrer(User $utilisateur, string $jeton): void
    {
        DB::transaction(function () use ($utilisateur, $jeton) {
            $this->appareils->enregistrer($utilisateur, $jeton, PlateformeAppareil::Android);
            $utilisateur->forceFill(['jeton_push' => $jeton])->save();
        });
    }

    /** Retire tous les jetons de l'utilisateur (notifications push désactivées). Sans effet si aucun jeton n'est enregistré. */
    public function retirer(User $utilisateur): void
    {
        $this->appareils->retirerTous($utilisateur);
        $utilisateur->refresh();
    }
}
