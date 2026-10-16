<?php

namespace App\Enums;

/** Nature d'un mouvement du solde d'un prestataire. */
enum TypeMouvementPortefeuille: string
{
    case Credit = 'credit';
    case Retrait = 'retrait';
    case RemboursementRetrait = 'remboursement_retrait';
}
