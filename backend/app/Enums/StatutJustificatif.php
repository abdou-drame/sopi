<?php

namespace App\Enums;

/** Étapes de vérification d'un justificatif par l'administrateur : EN_ATTENTE, VALIDE, REJETE. */
enum StatutJustificatif: string
{
    case EnAttente = 'en_attente';
    case Valide = 'valide';
    case Rejete = 'rejete';
}
