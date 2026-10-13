<?php

namespace App\Enums;

/** Pourquoi une réservation a été annulée (événement ReservationAnnulee). */
enum CauseAnnulation: string
{
    /** Annulation voulue par la personne qui annule (le client en M5.4). */
    case Demande = 'demande';

    /** Le prestataire a déclaré indisponible la plage du créneau (M5.7). */
    case Indisponibilite = 'indisponibilite';

    /** Le compte du prestataire ou du client a été suspendu ou banni par un administrateur (M9.3). */
    case Sanction = 'sanction';
}
