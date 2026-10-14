<?php

namespace App\Support;

/**
 * Recherche de texte partielle des listes d'administration : correspondance « contient », insensible à la casse
 * (lower() des deux côtés), jokers LIKE échappés. Comme la recherche de prestataires, elle n'ignore pas les accents et
 * la mise en minuscules des lettres accentuées dépend du moteur (PostgreSQL oui, SQLite non).
 */
final class RechercheTexte
{
    /** Caractère d'échappement des jokers LIKE (« ! » évite toute ambiguïté avec l'antislash selon les moteurs). */
    private const ECHAPPEMENT = '!';

    /** Texte saisi nettoyé : espaces réduits, minuscules. */
    public static function minuscules(string $texte): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $texte)));
    }

    /** Motif « contient » pour la clause renvoyée par {@see clause()}. */
    public static function motif(string $texte): string
    {
        return '%'.str_replace(
            [self::ECHAPPEMENT, '%', '_'],
            [self::ECHAPPEMENT.self::ECHAPPEMENT, self::ECHAPPEMENT.'%', self::ECHAPPEMENT.'_'],
            self::minuscules($texte),
        ).'%';
    }

    /** Clause SQL (un seul paramètre : le motif) pour une colonne, à passer à whereRaw / orWhereRaw. */
    public static function clause(string $colonne): string
    {
        return 'lower('.$colonne.") like ? escape '".self::ECHAPPEMENT."'";
    }
}
