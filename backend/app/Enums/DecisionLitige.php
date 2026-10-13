<?php

namespace App\Enums;

/** Décision finale de l'administrateur sur un litige (énumération DecisionLitige du cahier des charges, tableau 20). */
enum DecisionLitige: string
{
    case Rejet = 'rejet';
    case Avertissement = 'avertissement';
    case SuspensionTemporaire = 'suspension_temporaire';
    case BannissementDefinitif = 'bannissement_definitif';

    /** Sanction correspondante, ou null pour un rejet du signalement (aucune sanction). */
    public function typeSanction(): ?TypeSanction
    {
        return match ($this) {
            self::Rejet => null,
            self::Avertissement => TypeSanction::Avertissement,
            self::SuspensionTemporaire => TypeSanction::SuspensionTemporaire,
            self::BannissementDefinitif => TypeSanction::BannissementDefinitif,
        };
    }
}
