<?php

namespace App\Enums;

enum Role: string
{
    case Client = 'client';
    case Prestataire = 'prestataire';
    case Administrateur = 'administrateur';
}
