<?php

namespace App\Enums;

/** Opérateur mobile money qui reçoit le versement d'un retrait. */
enum OperateurRetrait: string
{
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';

    public function libelle(): string
    {
        return match ($this) {
            self::Wave => 'Wave',
            self::OrangeMoney => 'Orange Money',
        };
    }
}
