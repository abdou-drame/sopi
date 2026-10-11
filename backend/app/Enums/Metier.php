<?php

namespace App\Enums;

/**
 * Métiers proposés sur Sopi (CLAUDE.md, « Données de départ » : Mécanique, Coiffure, Pressing).
 * Stockés en minuscules sans accent ; libelle() donne le nom affichable.
 */
enum Metier: string
{
    case Mecanique = 'mecanique';
    case Coiffure = 'coiffure';
    case Pressing = 'pressing';

    public function libelle(): string
    {
        return match ($this) {
            self::Mecanique => 'Mécanique',
            self::Coiffure => 'Coiffure',
            self::Pressing => 'Pressing',
        };
    }
}
