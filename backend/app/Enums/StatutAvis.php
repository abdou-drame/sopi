<?php

namespace App\Enums;

/**
 * Statuts d'un avis (diagramme de classes) : PUBLIE, SIGNALE par le prestataire en attente de modération, SUPPRIME (masqué par l'administrateur).
 * Valeur calculée à partir des colonnes de modération (voir Avis::statut), jamais stockée.
 */
enum StatutAvis: string
{
    case Publie = 'publie';
    case Signale = 'signale';
    case Supprime = 'supprime';
}
