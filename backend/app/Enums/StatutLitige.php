<?php

namespace App\Enums;

/** Statuts d'un litige (cahier des charges, règles « Avis, litiges et sanctions ») : OUVERT, puis CLOTURE par la décision de l'administrateur. */
enum StatutLitige: string
{
    case Ouvert = 'ouvert';
    case Cloture = 'cloture';
}
