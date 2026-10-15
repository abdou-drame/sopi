<?php

namespace App\Enums;

/** Type d'une notification : identifie l'événement métier qui l'a produite (cahier des charges, section « Notifications »). */
enum TypeNotification: string
{
    case DossierDepose = 'dossier_depose';
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
        return in_array($this, [self::DossierDepose, self::DemandeAcceptee, self::DemandeRefusee, self::LitigeCloture, self::SanctionPosee, self::SanctionLevee], true);
    }

    /** Phrase d'introduction de l'e-mail, propre à chaque type (le détail est dans le corps de la notification). */
    public function accrocheEmail(): string
    {
        return match ($this) {
            self::DossierDepose => "Nous avons bien reçu votre dossier d'inscription.",
            self::DemandeAcceptee => 'Bonne nouvelle : votre dossier prestataire a été examiné.',
            self::DemandeRefusee => 'Votre dossier prestataire a été examiné.',
            self::ReservationNouvelleDemande => 'Une nouvelle demande de réservation vous attend.',
            self::ReservationAcceptee => 'Le prestataire a répondu à votre demande de réservation.',
            self::ReservationRefusee => 'Le prestataire a répondu à votre demande de réservation.',
            self::ReservationExpiree => 'Une de vos réservations a expiré.',
            self::ReservationAnnulee => 'Une réservation a été annulée.',
            self::ReservationConfirmee => 'Votre réservation est confirmée.',
            self::ReservationDemarree => 'Votre prestation a commencé.',
            self::ReservationTerminee => 'Votre prestation est terminée.',
            self::AvisRecu => 'Un client a laissé un avis sur votre travail.',
            self::AvisModere => "Un de vos avis a été examiné par l'équipe Sopi.",
            self::LitigeOuvert => 'Un litige a été ouvert sur une réservation.',
            self::LitigeCloture => "Un litige a été clôturé par l'équipe Sopi.",
            self::SanctionPosee => 'Une décision concerne votre compte Sopi.',
            self::SanctionLevee => 'Une décision concerne votre compte Sopi.',
        };
    }
}
