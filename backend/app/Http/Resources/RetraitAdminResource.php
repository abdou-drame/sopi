<?php

namespace App\Http\Resources;

use App\Enums\StatutRetrait;
use App\Models\Retrait;
use App\Services\RetraitsAdminService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Retrait vu par l'administrateur. Le téléphone de versement est masqué en liste (préfixe et deux derniers chiffres) et complet
 * dans le détail, qui est aussi le seul endroit de la référence DexPay. Montant en FCFA entier. Le prestataire doit être chargé.
 *
 * @mixin Retrait
 */
class RetraitAdminResource extends JsonResource
{
    public static $wrap = null;

    private bool $detail = false;

    public static function detail(Retrait $retrait): self
    {
        $ressource = new self($retrait);
        $ressource->detail = true;

        return $ressource;
    }

    public function toArray(Request $request): array
    {
        $ligne = [
            'id' => $this->id,
            'prestataire' => ['id' => $this->prestataire_id, 'userId' => $this->prestataire?->user_id, 'nomEntreprise' => $this->prestataire?->nom_entreprise],
            'montant' => PaiementAdminResource::montant($this->montant),
            'statut' => $this->statut->value,
            'operateur' => $this->operateur?->value,
            'bloque' => $this->statut === StatutRetrait::EnCours && $this->created_at->lte(RetraitsAdminService::limiteBlocage()),
            'dateDemande' => $this->created_at->toIso8601String(),
            'traiteLe' => $this->traite_le?->toIso8601String(),
            'recredite' => $this->recredite_le !== null,
        ];

        return $this->detail
            ? $ligne + [
                'telephoneVersement' => $this->telephone_versement,
                'referenceDexpay' => $this->reference_dexpay,
                'recrediteLe' => $this->recredite_le?->toIso8601String(),
            ]
            : $ligne + ['telephoneVersement' => self::masquer($this->telephone_versement)];
    }

    /** « +221771234567 » devient « +221*******67 » : quatre premiers caractères et deux derniers chiffres. */
    public static function masquer(?string $telephone): ?string
    {
        if ($telephone === null || mb_strlen($telephone) <= 6) {
            return $telephone === null ? null : str_repeat('*', mb_strlen($telephone));
        }

        return mb_substr($telephone, 0, 4).str_repeat('*', mb_strlen($telephone) - 6).mb_substr($telephone, -2);
    }
}
