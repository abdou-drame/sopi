<?php

namespace App\Enums;

/** Canaux d'envoi externes (la notification dans l'application existe toujours, elle est la ligne elle-même). */
enum Canal: string
{
    case Email = 'email';
    case Push = 'push';
}
