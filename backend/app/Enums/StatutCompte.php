<?php

namespace App\Enums;

/**
 * Statuts du compte (CLAUDE.md : EN_ATTENTE_VALIDATION, ACTIF, REFUSE, SUSPENDU, BANNI).
 * Seul un compte ACTIF peut s'authentifier.
 */
enum StatutCompte: string
{
    case EnAttenteValidation = 'en_attente';
    case Actif = 'actif';
    case Refuse = 'refuse';
    case Suspendu = 'suspendu';
    case Banni = 'banni';

    /** Message affiché quand la connexion est refusée pour ce statut. */
    public function messageRefus(): string
    {
        return match ($this) {
            self::EnAttenteValidation => 'Votre compte est en attente de validation.',
            self::Refuse => "Votre demande d'inscription a été refusée.",
            self::Suspendu => 'Votre compte est suspendu. Contactez le support.',
            self::Banni => 'Votre compte a été banni.',
            self::Actif => '',
        };
    }
}
