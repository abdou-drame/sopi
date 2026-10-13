<?php

namespace App\Enums;

/** Type d'une notification : identifie l'événement métier qui l'a produite (cahier des charges, section « Notifications »). */
enum TypeNotification: string
{
    case DemandeAcceptee = 'demande_acceptee';
    case DemandeRefusee = 'demande_refusee';
    case ReservationNouvelleDemande = 'reservation_nouvelle_demande';
    case ReservationAcceptee = 'reservation_acceptee';
    case ReservationRefusee = 'reservation_refusee';
    case ReservationExpiree = 'reservation_expiree';
    case ReservationAnnulee = 'reservation_annulee';
    case ReservationConfirmee = 'reservation_confirmee';
    case ReservationDemarree = 'reservation_demarree';
    case ReservationTerminee = 'reservation_terminee';
    case AvisRecu = 'avis_recu';
    case AvisModere = 'avis_modere';
    case LitigeOuvert = 'litige_ouvert';
    case LitigeCloture = 'litige_cloture';
    case SanctionPosee = 'sanction_posee';
    case SanctionLevee = 'sanction_levee';

    /**
     * Vrai si le message doit atteindre l'utilisateur même quand son compte n'est pas actif : le résultat de l'examen d'un dossier
     * (compte refusé ou tout juste activé) et les sanctions (le compte suspendu ou banni est précisément le sujet du message).
     */
    public function ignoreStatutDuCompte(): bool
    {
        return in_array($this, [self::DemandeAcceptee, self::DemandeRefusee, self::LitigeCloture, self::SanctionPosee, self::SanctionLevee], true);
    }
}
