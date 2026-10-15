<?php

namespace App\Services\Paiement;

/** Nature d'un événement reçu par webhook : ce qu'il faut en faire côté Sopi. */
enum GenreEvenement: string
{
    /** Résultat du paiement d'un acompte (réussi ou échoué). */
    case Paiement = 'paiement';
    /** Remboursement d'un acompte confirmé par le prestataire de paiement. */
    case Remboursement = 'remboursement';
    /** Résultat du versement d'un retrait. */
    case Versement = 'versement';
    /** Événement sans effet (paiement initié, événement inconnu). */
    case Ignore = 'ignore';
}
