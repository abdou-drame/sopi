<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationAnnulee;
use App\Exceptions\ReservationImpossibleException;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Suivi et annulation des réservations par leur client (RF06, RF07, RG13). L'acompte payé n'est pas remboursé. */
class SuiviReservationService
{
    public const PAR_PAGE = 20;

    public function lister(User $client, ?StatutReservation $statut = null): LengthAwarePaginator
    {
        return $client->reservations()
            ->with(['creneau', 'prestation.prestataire', 'paiements'])
            ->withExists(['paiements as a_paiement_reussi' => fn ($q) => $q->where('statut', StatutPaiement::Reussi->value)])
            ->when($statut, fn ($q) => $q->where('statut', $statut->value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE);
    }

    /** Réservation du client, ou null si elle n'existe pas ou appartient à quelqu'un d'autre. */
    public function trouver(User $client, string $id): ?Reservation
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return $client->reservations()->with(['creneau', 'prestation.prestataire', 'paiements'])
            ->withExists(['paiements as a_paiement_reussi' => fn ($q) => $q->where('statut', StatutPaiement::Reussi->value)])
            ->find($id);
    }

    /** Mêmes règles que l'annulation : statut annulable et début du créneau à au moins le délai configuré. */
    public static function peutAnnuler(Reservation $reservation): bool
    {
        return in_array($reservation->statut, StatutReservation::ANNULABLES_PAR_CLIENT, true)
            && $reservation->creneau->date_debut->greaterThanOrEqualTo(self::limiteAnnulation());
    }

    /**
     * Annule la réservation et libère son créneau dans UNE transaction ; l'acompte éventuel reste acquis.
     *
     * @throws ReservationImpossibleException 404 (introuvable) ou 409 (statut non annulable, trop tard, créneau non libérable)
     */
    public function annuler(User $client, string $id): Reservation
    {
        $reservation = $this->trouver($client, $id)
            ?? throw new ReservationImpossibleException('Réservation introuvable.', Response::HTTP_NOT_FOUND);

        DB::transaction(function () use ($reservation, $client) {
            if (! $reservation->annulerParClientSiPossible($client->id, self::limiteAnnulation())) {
                throw $this->refus($reservation, $client);
            }
            if (! $reservation->creneau->libererPourReservation($reservation)) {
                throw new ReservationImpossibleException('Cette réservation ne peut plus être annulée.', Response::HTTP_CONFLICT);
            }
        });

        ReservationAnnulee::dispatch($reservation, Role::Client);

        return $reservation->load(['creneau', 'prestation.prestataire', 'paiements']);
    }

    /** Une réservation dont le créneau commence avant cet instant (UTC) n'est plus annulable. */
    private static function limiteAnnulation(): CarbonInterface
    {
        return now()->utc()->addHours((int) config('sopi.reservations.annulation_client_heures'));
    }

    /** Explique l'échec de la mise à jour conditionnelle (relecture APRÈS l'écriture refusée, pour choisir le message). */
    private function refus(Reservation $reservation, User $client): ReservationImpossibleException
    {
        $actuelle = Reservation::query()->whereKey($reservation->id)->where('client_id', $client->id)->first();

        return $actuelle !== null && in_array($actuelle->statut, StatutReservation::ANNULABLES_PAR_CLIENT, true)
            ? new ReservationImpossibleException('L\'annulation n\'est plus possible moins de 2 heures avant le créneau.', Response::HTTP_CONFLICT)
            : new ReservationImpossibleException('Cette réservation ne peut plus être annulée.', Response::HTTP_CONFLICT);
    }
}
