<?php

namespace App\Support;

/**
 * Numéros mobiles sénégalais : 9 chiffres commençant par 70, 75, 76, 77 ou 78,
 * avec ou sans indicatif (+221, 221, 00221). Forme stockée : +221XXXXXXXXX.
 */
final class TelephoneSenegal
{
    private const MOTIF = '/^(?:\+?221|00221)?(7[05678]\d{7})$/';

    /** Renvoie le numéro normalisé, ou null s'il n'est pas un mobile sénégalais valide. */
    public static function normaliser(?string $numero): ?string
    {
        if ($numero === null) {
            return null;
        }

        $nettoye = preg_replace('/[\s.\-()]/', '', $numero);

        return preg_match(self::MOTIF, $nettoye, $m) ? '+221'.$m[1] : null;
    }
}
