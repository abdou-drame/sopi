<?php

namespace App\Http\Resources;

use App\Models\Retrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Retrait vu par son prestataire. N'expose NI la référence du prestataire de paiement, NI la garde technique de recrédit.
 * Le numéro de versement est masqué (seuls les 3 derniers chiffres restent visibles) : c'est une donnée personnelle qui
 * circule dans l'historique, et le prestataire reconnaît son numéro à sa fin sans qu'une capture d'écran ou un jeton volé
 * ne le révèle en entier.
 *
 * @mixin Retrait
 */
class RetraitResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'montant' => (float) $this->montant,
            'statut' => $this->statut->value,
            'telephoneVersement' => self::masquer($this->telephone_versement),
            'dateDemande' => $this->created_at->toIso8601String(),
            'dateTraitement' => $this->traite_le?->toIso8601String(),
        ];
    }

    /** « +221771234567 » devient « +221******567 » : seuls l'indicatif et les 3 derniers chiffres restent. */
    public static function masquer(string $numero): string
    {
        $fin = mb_substr($numero, -3);
        $debut = str_starts_with($numero, '+221') ? '+221' : '';

        return $debut.str_repeat('*', max(0, mb_strlen($numero) - mb_strlen($debut) - 3)).$fin;
    }
}
