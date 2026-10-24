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
    case RappelRendezVous = 'rappel_rendez_vous';
    case AvisRecu = 'avis_recu';
    case AvisModere = 'avis_modere';
    case LitigeOuvert = 'litige_ouvert';
    case LitigeCloture = 'litige_cloture';
    case SanctionPosee = 'sanction_posee';
    case SanctionLevee = 'sanction_levee';
    case ReinitialisationMotDePasse = 'reinitialisation_mot_de_passe';
    case MotDePasseModifie = 'mot_de_passe_modifie';

    /**
     * Vrai si le message doit atteindre l'utilisateur même quand son compte n'est pas actif : le résultat de l'examen d'un dossier
     * (compte refusé ou tout juste activé) et les sanctions (le compte suspendu ou banni est précisément le sujet du message).
     */
    public function ignoreStatutDuCompte(): bool
    {
        return in_array($this, [self::DossierDepose, self::DemandeAcceptee, self::DemandeRefusee, self::LitigeCloture, self::SanctionPosee, self::SanctionLevee], true);
    }

    /**
     * Vrai si l'e-mail contient un lien secret (transmis chiffré, jamais stocké en clair). Le résultat de l'examen du dossier
     * porte le lien de suivi (mémoire, 3.2.2), pour que le prestataire refusé, qui ne peut pas se connecter, en lise le motif.
     */
    public function aUnLien(): bool
    {
        return in_array($this, [self::DossierDepose, self::DemandeAcceptee, self::DemandeRefusee, self::ReinitialisationMotDePasse], true);
    }

    public function libelleLien(): string
    {
        return $this === self::ReinitialisationMotDePasse ? 'Choisir un nouveau mot de passe' : 'Suivre mon dossier';
    }

    public function noteLien(): string
    {
        return $this === self::ReinitialisationMotDePasse
            ? 'Ce lien est personnel, valable 60 minutes et utilisable une seule fois : ne le partagez pas. Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.'
            : 'Ce lien est personnel : ne le partagez pas. Vous y suivrez la validation de votre compte (en attente, validé ou refusé).';
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
            self::RappelRendezVous => 'Un rendez-vous approche.',
            self::AvisRecu => 'Un client a laissé un avis sur votre travail.',
            self::AvisModere => "Un de vos avis a été examiné par l'équipe Sopi.",
            self::LitigeOuvert => 'Un litige a été ouvert sur une réservation.',
            self::LitigeCloture => "Un litige a été clôturé par l'équipe Sopi.",
            self::SanctionPosee => 'Une décision concerne votre compte Sopi.',
            self::SanctionLevee => 'Une décision concerne votre compte Sopi.',
            self::ReinitialisationMotDePasse => 'Vous avez demandé à réinitialiser votre mot de passe.',
            self::MotDePasseModifie => 'La sécurité de votre compte Sopi.',
        };
    }
}
