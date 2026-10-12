<?php

namespace App\Enums;

/**
 * Statuts d'un paiement d'acompte (diagramme de classes) : EN_ATTENTE, puis REUSSI ou ECHOUE ; REMBOURSE après un REUSSI.
 */
enum StatutPaiement: string
{
    case EnAttente = 'en_attente';
    case Reussi = 'reussi';
    case Echoue = 'echoue';
    case Rembourse = 'rembourse';

    /** Statuts qui comptent comme paiement actif de la réservation (index unique partiel de la table). */
    public const ACTIFS = [self::EnAttente, self::Reussi];

    /** Un paiement actif est en cours ou réussi ; un paiement échoué ou remboursé permet d'en créer un nouveau. */
    public function estActif(): bool
    {
        return in_array($this, self::ACTIFS, true);
    }
}
