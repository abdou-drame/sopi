<?php

namespace App\Services;

use App\Enums\GroupeReservation;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationAcceptee;
use App\Events\Reservation\ReservationRefusee;
use App\Exceptions\ReservationImpossibleException;
use App\Models\Prestataire;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Demandes de réservation reçues par un prestataire : consultation, acceptation, refus (RF05, RG10 à RG12). */
class ReponseReservationService
{
    public const PAR_PAGE = 20;

    /** Demandes des créneaux du prestataire, les plus récentes d'abord, filtrées par statut si demandé. */
    public function lister(Prestataire $prestataire, ?StatutReservation $statut = null, ?GroupeReservation $groupe = null): LengthAwarePaginator
    {
        $requete = $this->demandes($prestataire)
            ->with(['creneau', 'prestation', 'client', 'paiements'])
            ->when($statut, fn (Builder $q) => $q->where('statut', $statut->value));

        if ($groupe !== null) {
            $groupe->appliquer($requete);
        } else {
            $requete->orderByDesc('created_at')->orderByDesc('id');
        }

        return $requete->paginate(self::PAR_PAGE);
    }

    /** Détail d'une demande, ou null si elle n'existe pas ou n'appartient pas à ce prestataire. */
    public function trouver(Prestataire $prestataire, string $id): ?Reservation
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return $this->demandes($prestataire)->with(['creneau', 'prestation', 'client', 'paiements'])->find($id);
    }

    /**
     * Accepte une demande EN_ATTENTE : statut ACCEPTEE, acceptee_le = maintenant (départ du délai de paiement).
     * Le créneau reste réservé.
     *
     * @throws ReservationImpossibleException 404 (demande introuvable) ou 409 (déjà traitée, ou délai de réponse dépassé)
     */
    public function accepter(Prestataire $prestataire, string $id): Reservation
    {
        $reservation = $this->trouverOuEchouer($prestataire, $id);

        DB::transaction(function () use ($reservation) {
            if (! $reservation->accepterSiEnAttente($this->limiteReponse())) {
                throw $this->refus($reservation);
            }
        });

        ReservationAcceptee::dispatch($reservation);

        return $reservation;
    }

    /**
     * Refuse une demande EN_ATTENTE avec un motif et libère le créneau, dans UNE transaction : si la libération échoue,
     * la demande reste EN_ATTENTE.
     *
     * @throws ReservationImpossibleException 404 (demande introuvable) ou 409 (déjà traitée, délai dépassé, créneau non libérable)
     */
    public function refuser(Prestataire $prestataire, string $id, string $motif): Reservation
    {
        $reservation = $this->trouverOuEchouer($prestataire, $id);

        DB::transaction(function () use ($reservation, $motif) {
            if (! $reservation->refuserSiEnAttente($motif, $this->limiteReponse())) {
                throw $this->refus($reservation);
            }
            if (! $reservation->creneau->libererPourReservation($reservation)) {
                throw new ReservationImpossibleException('Cette demande ne peut pas être refusée : son créneau n\'est plus réservé.', Response::HTTP_CONFLICT);
            }
        });

        ReservationRefusee::dispatch($reservation);

        return $reservation->load('creneau');
    }

    private function demandes(Prestataire $prestataire): Builder
    {
        return Reservation::query()->whereHas('creneau', fn (Builder $q) => $q->where('prestataire_id', $prestataire->id));
    }

    private function trouverOuEchouer(Prestataire $prestataire, string $id): Reservation
    {
        return $this->trouver($prestataire, $id)
            ?? throw new ReservationImpossibleException('Réservation introuvable.', Response::HTTP_NOT_FOUND);
    }

    /** Date limite (UTC) de réponse du prestataire : création + délai de réponse. Null si la réservation n'est plus EN_ATTENTE. */
    public static function echeanceReponse(Reservation $reservation): ?CarbonInterface
    {
        if ($reservation->statut !== StatutReservation::EnAttente) {
            return null;
        }

        return $reservation->created_at->copy()->utc()->addHours((int) config('sopi.reservations.delai_reponse_heures'));
    }

    /** Une demande créée avant cet instant a dépassé le délai de réponse. */
    private function limiteReponse(): CarbonInterface
    {
        return now()->subHours((int) config('sopi.reservations.delai_reponse_heures'));
    }

    /** Explique l'échec de la mise à jour conditionnelle (lecture APRÈS l'écriture refusée, pour choisir le message). */
    private function refus(Reservation $reservation): ReservationImpossibleException
    {
        $statut = Reservation::query()->whereKey($reservation->id)->first()?->statut;

        return $statut === StatutReservation::EnAttente
            ? new ReservationImpossibleException('Le délai de réponse est dépassé.', Response::HTTP_CONFLICT)
            : new ReservationImpossibleException('Cette demande a déjà reçu une réponse.', Response::HTTP_CONFLICT);
    }
}
