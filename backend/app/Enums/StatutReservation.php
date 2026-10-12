<?php

namespace App\Enums;

/**
 * Statuts d'une réservation (diagramme de classes) : EN_ATTENTE → ACCEPTEE (en attente de paiement) → CONFIRMEE
 * → EN_COURS → TERMINEE ; issues REFUSEE et ANNULEE.
 */
enum StatutReservation: string
{
    case EnAttente = 'en_attente';
    case Acceptee = 'acceptee';
    case Confirmee = 'confirmee';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
    case Refusee = 'refusee';
    case Annulee = 'annulee';

    /** Statuts qui ne comptent plus : ils ne bloquent pas le créneau (index unique partiel de la table). */
    public const INACTIFS = [self::Refusee, self::Annulee];

    /** Une réservation active occupe son créneau : tous les statuts sauf REFUSEE et ANNULEE. */
    public function estActif(): bool
    {
        return ! in_array($this, self::INACTIFS, true);
    }
}
