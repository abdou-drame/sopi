<?php

namespace App\Services;

/**
 * Code de réservation : 6 caractères majuscules et chiffres, tirés avec un générateur cryptographique (random_int).
 * Alphabet sans caractères ambigus (ni 0, O, 1, I, L) : 23 lettres et 8 chiffres, compatible avec [A-Z0-9]{6}.
 * L'unicité finale est garantie par l'index unique de reservations.code_reservation.
 */
class GenerateurCodeReservation
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LONGUEUR = 6;

    public function generer(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LONGUEUR; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
