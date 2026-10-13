<?php

namespace App\Enums;

/** Décision disciplinaire d'une sanction (cahier des charges, RF19) : avertissement, suspension temporaire, bannissement définitif. */
enum TypeSanction: string
{
    case Avertissement = 'avertissement';
    case SuspensionTemporaire = 'suspension_temporaire';
    case BannissementDefinitif = 'bannissement_definitif';

    /** Une suspension ou un bannissement bloque le compte ; un avertissement ne bloque rien. */
    public function bloque(): bool
    {
        return $this !== self::Avertissement;
    }

    /** Statut que prend le compte tant que la sanction est active, ou null pour un avertissement. */
    public function statutCompte(): ?StatutCompte
    {
        return match ($this) {
            self::Avertissement => null,
            self::SuspensionTemporaire => StatutCompte::Suspendu,
            self::BannissementDefinitif => StatutCompte::Banni,
        };
    }
}
