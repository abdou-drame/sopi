<?php

namespace App\Enums;

/**
 * Statuts d'un créneau (diagramme de classes) : LIBRE, VERROUILLE (réservation en cours, verrou temporaire),
 * RESERVE, INDISPONIBLE (bloqué par le prestataire).
 */
enum StatutCreneau: string
{
    case Libre = 'libre';
    case Verrouille = 'verrouille';
    case Reserve = 'reserve';
    case Indisponible = 'indisponible';
}
