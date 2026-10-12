<?php

namespace App\Services;

use App\Enums\CauseAnnulation;
use App\Enums\Role;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationAnnulee;
use App\Exceptions\ReservationImpossibleException;
use App\Models\Prestataire;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Annulation par le prestataire d'une réservation ACCEPTEE ou CONFIRMEE (M6.5, RG de remboursement). Toute l'écriture est dans
 * UNE transaction : réservation ANNULEE (motif dans motif_refus), créneau libéré, paiement REUSSI marqué « remboursement dû ».
 * Le remboursement lui-même (appel externe) a lieu APRÈS la transaction ; s'il échoue, la commande planifiée le reprend.
 */
class AnnulationPrestataireService
{
    public function __construct(
        private readonly ReponseReservationService $reservations,
        private readonly RemboursementPaiementService $remboursements,
    ) {}

    /**
     * @throws ReservationImpossibleException 404 (introuvable) ou 409 (statut non annulable, créneau commencé, situation changée)
     */
    public function annuler(Prestataire $prestataire, string $id, string $motif): Reservation
    {
        $reservation = $this->reservations->trouver($prestataire, $id)
            ?? throw new ReservationImpossibleException('Réservation introuvable.', Response::HTTP_NOT_FOUND);

        $paiements = DB::transaction(function () use ($reservation, $prestataire, $motif) {
            if (! $reservation->annulerParPrestataireSiPossible($prestataire->id, $motif)) {
                throw $this->refus($reservation);
            }
            if (! $reservation->creneau->libererPourReservation($reservation)) {
                throw new ReservationImpossibleException('La situation a changé, veuillez réessayer.', Response::HTTP_CONFLICT);
            }

            $payes = $reservation->paiements()->where('statut', StatutPaiement::Reussi->value)->get();
            foreach ($payes as $paiement) {
                $this->remboursements->marquerDu($paiement);
            }

            return $payes;
        });

        ReservationAnnulee::dispatch($reservation, Role::Prestataire, CauseAnnulation::Demande);

        $this->remboursements->tenterTous($paiements);

        return $reservation->load('creneau');
    }

    /** Explique l'échec de la mise à jour conditionnelle (relecture APRÈS l'écriture refusée, pour choisir le message). */
    private function refus(Reservation $reservation): ReservationImpossibleException
    {
        $statut = Reservation::query()->whereKey($reservation->id)->first()?->statut;

        return match (true) {
            $statut === StatutReservation::EnAttente => new ReservationImpossibleException('Pour une demande en attente, utilisez le refus.', Response::HTTP_CONFLICT),
            in_array($statut, [StatutReservation::Acceptee, StatutReservation::Confirmee], true) => new ReservationImpossibleException('Le créneau a déjà commencé.', Response::HTTP_CONFLICT),
            default => new ReservationImpossibleException('Cette réservation ne peut plus être annulée.', Response::HTTP_CONFLICT),
        };
    }
}
