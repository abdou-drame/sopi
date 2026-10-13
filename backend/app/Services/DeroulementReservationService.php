<?php

namespace App\Services;

use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationDemarree;
use App\Events\Reservation\ReservationTerminee;
use App\Exceptions\CreditSoldeImpossibleException;
use App\Exceptions\ReservationImpossibleException;
use App\Models\Prestataire;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Déroulement de la prestation : démarrage avec le code du client, puis fin (RF08, RF09, RG14 à RG16). */
class DeroulementReservationService
{
    public function __construct(
        private readonly ReponseReservationService $demandes,
        private readonly CreditSoldeService $credits,
    ) {}

    /**
     * CONFIRMEE → EN_COURS si le code est le bon. Le code doit déjà être normalisé (majuscules, sans espaces).
     *
     * @throws ReservationImpossibleException 404 (introuvable) ou 409 (statut différent de CONFIRMEE)
     * @throws ValidationException 422 (code incorrect)
     */
    public function demarrer(Prestataire $prestataire, string $id, string $code): Reservation
    {
        $reservation = $this->trouverOuEchouer($prestataire, $id);

        DB::transaction(function () use ($reservation, $prestataire, $code) {
            if (! $reservation->demarrerSiCodeValide($code, $prestataire->id)) {
                throw $this->refusDemarrage($reservation);
            }
        });

        ReservationDemarree::dispatch($reservation);

        return $reservation;
    }

    /**
     * EN_COURS → TERMINEE et crédit du solde du prestataire (acompte − commission) dans la MÊME transaction : si le crédit
     * échoue, la réservation reste EN_COURS. Le créneau reste réservé. L'événement part après validation.
     *
     * @throws ReservationImpossibleException 404 (introuvable) ou 409 (statut différent de EN_COURS)
     * @throws CreditSoldeImpossibleException 500 (le crédit a échoué, rien n'est enregistré)
     */
    public function terminer(Prestataire $prestataire, string $id): Reservation
    {
        $reservation = $this->trouverOuEchouer($prestataire, $id);

        DB::transaction(function () use ($reservation, $prestataire) {
            $this->passerATerminee($reservation, $prestataire);
            $this->credits->crediter($reservation);
        });

        ReservationTerminee::dispatch($reservation);

        return $reservation;
    }

    /** Changement de statut de « terminer » (409 si la réservation n'est plus EN_COURS : le crédit n'est alors jamais tenté). */
    protected function passerATerminee(Reservation $reservation, Prestataire $prestataire): void
    {
        if (! $reservation->terminerSiEnCours($prestataire->id)) {
            throw new ReservationImpossibleException('Cette réservation ne peut pas être terminée.', Response::HTTP_CONFLICT);
        }
    }

    private function trouverOuEchouer(Prestataire $prestataire, string $id): Reservation
    {
        return $this->demandes->trouver($prestataire, $id)
            ?? throw new ReservationImpossibleException('Réservation introuvable.', Response::HTTP_NOT_FOUND);
    }

    /** Explique l'échec de la mise à jour conditionnelle (relecture APRÈS l'écriture refusée, pour choisir le message). */
    private function refusDemarrage(Reservation $reservation): \Throwable
    {
        $statut = Reservation::query()->whereKey($reservation->id)->first()?->statut;

        return $statut === StatutReservation::Confirmee
            ? ValidationException::withMessages(['code' => ['Code de réservation incorrect.']])
            : new ReservationImpossibleException('Cette réservation ne peut pas être démarrée.', Response::HTTP_CONFLICT);
    }
}
