<?php

namespace App\Enums;

/**
 * Statuts d'un retrait de gains (diagramme de classes) : EN_COURS, puis REUSSI ou ECHOUE.
 */
enum StatutRetrait: string
{
    case EnCours = 'en_cours';
    case Reussi = 'reussi';
    case Echoue = 'echoue';
}
