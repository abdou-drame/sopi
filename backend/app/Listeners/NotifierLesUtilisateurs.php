<?php

namespace App\Listeners;

use App\Enums\CauseAnnulation;
use App\Enums\CauseExpiration;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Enums\TypeNotification;
use App\Enums\TypeSanction;
use App\Events\AvisDepose;
use App\Events\AvisModere;
use App\Events\DemandePrestataireAcceptee;
use App\Events\DemandePrestataireRefusee;
use App\Events\LitigeCloture;
use App\Events\LitigeOuvert;
use App\Events\Reservation\RappelRendezVous;
use App\Events\Reservation\ReservationAcceptee;
use App\Events\Reservation\ReservationAnnulee;
use App\Events\Reservation\ReservationConfirmee;
use App\Events\Reservation\ReservationCreee;
use App\Events\Reservation\ReservationDemarree;
use App\Events\Reservation\ReservationExpiree;
use App\Events\Reservation\ReservationRefusee;
use App\Events\Reservation\ReservationTerminee;
use App\Events\SanctionLevee;
use App\Events\SanctionPosee;
use App\Models\Litige;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Crée les notifications (M10.2) à partir des événements métier. Les événements sont publiés après validation de la transaction :
 * une action annulée ne notifie personne. Chaque méthode est protégée : un échec ici ne doit JAMAIS faire échouer l'action métier
 * déjà validée (la notification est perdue, l'incident est journalisé avec la classe de l'erreur seulement).
 *
 * Textes courts, sans donnée sensible : jamais de montant, de téléphone ni de secret (ils partent aussi par push). Le code de
 * réservation n'est communiqué qu'au client, dans la confirmation du paiement (mémoire, 3.3.4). Le client est présenté au prestataire par son prénom et l'initiale de son nom, le prestataire par son entreprise.
 * Les méthodes ne commencent pas par « handle » : l'enregistrement est explicite (AppServiceProvider), pas par découverte.
 */
class NotifierLesUtilisateurs
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function surReservationCreee(ReservationCreee $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $r = $this->charger($evenement->reservation);
            $heures = (int) config('sopi.reservations.delai_reponse_heures');
            $this->notifications->creer(
                $r->creneau->prestataire->user, TypeNotification::ReservationNouvelleDemande, 'Nouvelle demande de réservation',
                "{$this->nomPublicClient($r->client)} demande « {$r->prestation->nom} » le {$this->date($r)}. Répondez dans les {$heures} heures.",
                $this->donneesReservation($r),
            );
        });
    }

    public function surReservationAcceptee(ReservationAcceptee $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $r = $this->charger($evenement->reservation);
            $heures = (int) config('sopi.reservations.delai_paiement_heures');
            $this->notifications->creer(
                $r->client, TypeNotification::ReservationAcceptee, 'Demande acceptée',
                "{$this->entreprise($r)} a accepté « {$r->prestation->nom} » le {$this->date($r)}. Payez l'acompte dans les {$heures} heures pour confirmer.",
                $this->donneesReservation($r),
            );
        });
    }

    public function surReservationRefusee(ReservationRefusee $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $r = $this->charger($evenement->reservation);
            $this->notifications->creer(
                $r->client, TypeNotification::ReservationRefusee, 'Demande refusée',
                "{$this->entreprise($r)} ne peut pas assurer « {$r->prestation->nom} » le {$this->date($r)}."
                    .(filled($r->motif_refus) ? " Motif : {$r->motif_refus}" : '').' Choisissez un autre créneau.',
                $this->donneesReservation($r),
            );
        });
    }

    public function surReservationExpiree(ReservationExpiree $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $r = $this->charger($evenement->reservation);
            $corps = match ($evenement->cause) {
                CauseExpiration::SansReponse => "{$this->entreprise($r)} n'a pas répondu à temps : votre demande « {$r->prestation->nom} » du {$this->date($r)} est annulée.",
                CauseExpiration::Impayee => "L'acompte n'a pas été payé à temps : votre réservation « {$r->prestation->nom} » du {$this->date($r)} est annulée.",
            };
            $this->notifications->creer($r->client, TypeNotification::ReservationExpiree, 'Réservation expirée', $corps, $this->donneesReservation($r));
        });
    }

    public function surReservationAnnulee(ReservationAnnulee $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $r = $this->charger($evenement->reservation);
            $prestataire = $r->creneau->prestataire->user;
            $donnees = $this->donneesReservation($r);
            $prestation = $r->prestation->nom;

            if ($evenement->cause === CauseAnnulation::Demande && $evenement->par === Role::Client) {
                // Annulation par le client : le prestataire est prévenu (l'acompte n'est pas remboursé au client).
                $this->notifications->creer($prestataire, TypeNotification::ReservationAnnulee, 'Réservation annulée',
                    "{$this->nomPublicClient($r->client)} a annulé « {$prestation} » du {$this->date($r)}.", $donnees);

                return;
            }

            // Annulation par le prestataire, indisponibilité ou sanction : remboursement intégral. En cas de sanction, on prévient
            // la partie qui n'est pas sanctionnée (la sanction elle-même est notifiée séparément à la personne visée).
            $clientEstVise = $evenement->cause === CauseAnnulation::Sanction && $prestataire->statut === StatutCompte::Actif;
            if ($clientEstVise) {
                $this->notifications->creer($prestataire, TypeNotification::ReservationAnnulee, 'Réservation annulée',
                    "« {$prestation} » du {$this->date($r)} est annulée : le compte du client fait l'objet d'une mesure de l'administration.", $donnees);

                return;
            }

            $this->notifications->creer($r->client, TypeNotification::ReservationAnnulee, 'Réservation annulée',
                "{$this->entreprise($r)} ne peut pas honorer « {$prestation} » du {$this->date($r)} : la réservation est annulée et votre acompte sera remboursé.", $donnees);
        });
    }

    public function surReservationConfirmee(ReservationConfirmee $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $r = $this->charger($evenement->reservation);
            $this->notifications->creer(
                $r->creneau->prestataire->user, TypeNotification::ReservationConfirmee, 'Réservation confirmée',
                "L'acompte est payé : la réservation « {$r->prestation->nom} » de {$this->nomPublicClient($r->client)} le {$this->date($r)} est confirmée.",
                $this->donneesReservation($r),
            );
            // Mémoire 3.3.4 (étape 6) : le client reçoit la confirmation du paiement avec son code de réservation.
            $this->notifications->creer(
                $r->client, TypeNotification::ReservationConfirmee, 'Réservation confirmée',
                "Votre acompte est bien reçu : « {$r->prestation->nom} » chez {$this->entreprise($r)} le {$this->date($r)} est confirmée. Votre code de réservation : {$r->code_reservation}. Donnez-le au prestataire à votre arrivée.",
                $this->donneesReservation($r),
            );
        });
    }

    public function surReservationDemarree(ReservationDemarree $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $r = $this->charger($evenement->reservation);
            $this->notifications->creer(
                $r->client, TypeNotification::ReservationDemarree, 'Prestation démarrée',
                "{$this->entreprise($r)} a démarré la prestation « {$r->prestation->nom} ».",
                $this->donneesReservation($r),
            );
        });
    }

    public function surReservationTerminee(ReservationTerminee $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $r = $this->charger($evenement->reservation);
            $this->notifications->creer(
                $r->client, TypeNotification::ReservationTerminee, 'Prestation terminée',
                "La prestation « {$r->prestation->nom} » chez {$this->entreprise($r)} est terminée. Vous pouvez laisser un avis.",
                $this->donneesReservation($r),
            );
        });
    }

    /** RF20 : rappel court au client et au prestataire (le prestataire voit le client par son prénom et l'initiale de son nom). */
    public function surRappelRendezVous(RappelRendezVous $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $r = $this->charger($evenement->reservation);
            $donnees = $this->donneesReservation($r);
            $this->notifications->creer(
                $r->client, TypeNotification::RappelRendezVous, 'Rappel de rendez-vous',
                "Rappel : « {$r->prestation->nom} » chez {$this->entreprise($r)} le {$this->date($r)}.",
                $donnees,
            );
            $this->notifications->creer(
                $r->creneau->prestataire->user, TypeNotification::RappelRendezVous, 'Rappel de rendez-vous',
                "Rappel : « {$r->prestation->nom} » avec {$this->nomPublicClient($r->client)} le {$this->date($r)}.",
                $donnees,
            );
        });
    }

    public function surAvisDepose(AvisDepose $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $avis = $evenement->avis->loadMissing('client:id,nom,prenom', 'prestataire.user', 'reservation.prestation');
            $this->notifications->creer(
                $avis->prestataire->user, TypeNotification::AvisRecu, 'Nouvel avis',
                "{$this->nomPublicClient($avis->client)} a laissé un avis de {$avis->note}/5 sur « {$avis->reservation->prestation->nom} ».",
                ['avisId' => $avis->id, 'reservationId' => $avis->reservation_id],
            );
        });
    }

    public function surAvisModere(AvisModere $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $avis = $evenement->avis->loadMissing('prestataire.user');
            $corps = $evenement->masque
                ? 'L\'administration a supprimé un avis qui vous concerne.'
                : 'L\'administration a examiné le signalement d\'un avis et l\'a conservé.';
            $this->notifications->creer($avis->prestataire->user, TypeNotification::AvisModere, 'Avis examiné', $corps, ['avisId' => $avis->id]);
        });
    }

    public function surLitigeOuvert(LitigeOuvert $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $litige = $this->chargerLitige($evenement->litige);
            $this->notifications->creer(
                $litige->prestataire->user, TypeNotification::LitigeOuvert, 'Litige ouvert',
                "Un litige a été ouvert sur « {$litige->reservation->prestation->nom} » du {$this->date($litige->reservation)}. Vous pouvez répondre dans l'application.",
                ['litigeId' => $litige->id, 'reservationId' => $litige->reservation_id],
            );
        });
    }

    /**
     * Litige clôturé : la partie sanctionnée reçoit la notification de la sanction (surSanctionPosee) ; l'autre partie est prévenue
     * ici. Un rejet du signalement n'a aucune sanction : le client et le prestataire sont prévenus.
     */
    public function surLitigeCloture(LitigeCloture $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $litige = $this->chargerLitige($evenement->litige);
            $sanction = $litige->sanction()->first();
            $donnees = ['litigeId' => $litige->id, 'reservationId' => $litige->reservation_id];
            $prestation = $litige->reservation->prestation->nom;

            $destinataires = [
                'client' => $litige->client,
                'prestataire' => $litige->prestataire->user,
            ];
            if ($sanction !== null) {
                $destinataires = array_filter($destinataires, fn (User $u) => $u->id !== $sanction->user_id);
            }

            foreach ($destinataires as $destinataire) {
                $corps = $sanction === null
                    ? "Le litige sur « {$prestation} » est clôturé : le signalement n'a pas été retenu."
                    : "Le litige sur « {$prestation} » est clôturé : l'administration a pris une décision.";
                $this->notifications->creer($destinataire, TypeNotification::LitigeCloture, 'Litige clôturé', $corps, $donnees);
            }
        });
    }

    public function surSanctionPosee(SanctionPosee $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $sanction = $evenement->sanction->loadMissing('user');
            $corps = match ($sanction->type) {
                TypeSanction::Avertissement => 'Vous avez reçu un avertissement de l\'administration. Consultez l\'application pour le détail.',
                TypeSanction::SuspensionTemporaire => 'Votre compte est suspendu jusqu\'au '.$sanction->date_fin?->format('d/m/Y').'.',
                TypeSanction::BannissementDefinitif => 'Votre compte a été banni définitivement.',
            };
            $this->notifications->creer($sanction->user, TypeNotification::SanctionPosee, 'Sanction sur votre compte', $corps, ['sanctionId' => $sanction->id]);
        });
    }

    public function surSanctionLevee(SanctionLevee $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $sanction = $evenement->sanction->loadMissing('user');
            $corps = match ($sanction->type) {
                TypeSanction::Avertissement => 'Un avertissement a été retiré de votre compte.',
                default => 'La sanction est levée : vous pouvez de nouveau utiliser votre compte.',
            };
            $this->notifications->creer($sanction->user, TypeNotification::SanctionLevee, 'Sanction levée', $corps, ['sanctionId' => $sanction->id]);
        });
    }

    public function surDemandePrestataireAcceptee(DemandePrestataireAcceptee $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $this->notifications->creer(
                $evenement->prestataire->loadMissing('user')->user, TypeNotification::DemandeAcceptee, 'Dossier validé',
                'Votre dossier est validé : votre compte prestataire est actif. Vous pouvez vous connecter.',
            );
        });
    }

    public function surDemandePrestataireRefusee(DemandePrestataireRefusee $evenement): void
    {
        $this->proteger(function () use ($evenement) {
            $this->notifications->creer(
                $evenement->prestataire->loadMissing('user')->user, TypeNotification::DemandeRefusee, 'Dossier refusé',
                'Votre dossier n\'a pas été retenu. Le motif est visible dans le suivi de votre dossier.',
            );
        });
    }

    private function proteger(callable $creation): void
    {
        try {
            $creation();
        } catch (Throwable $e) {
            Log::warning('Création de notification échouée.', ['erreur' => $e::class]);
        }
    }

    private function charger(Reservation $reservation): Reservation
    {
        return $reservation->loadMissing('client', 'prestation', 'creneau.prestataire.user');
    }

    private function chargerLitige(Litige $litige): Litige
    {
        return $litige->loadMissing('client', 'prestataire.user', 'reservation.prestation', 'reservation.creneau');
    }

    private function nomPublicClient(User $client): string
    {
        return trim($client->prenom).' '.mb_strtoupper(mb_substr(trim($client->nom), 0, 1)).'.';
    }

    private function entreprise(Reservation $reservation): string
    {
        return $reservation->creneau->prestataire->nom_entreprise;
    }

    private function date(Reservation $reservation): string
    {
        return $reservation->creneau->date_debut->format('d/m/Y à H:i');
    }

    /** @return array<string, string> */
    private function donneesReservation(Reservation $reservation): array
    {
        return ['reservationId' => $reservation->id];
    }
}
