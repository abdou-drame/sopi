<?php

namespace App\Enums;

/** Système de l'appareil qui reçoit les notifications push. */
enum PlateformeAppareil: string
{
    case Android = 'android';
    case Ios = 'ios';
}
