<?php

namespace App\Services;

use App\Enums\StatutReservation;
use App\Events\AvisDepose;
use App\Exceptions\ReservationImpossibleException;
use App\Models\Avis;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Dépôt d'un avis par le client d'une réservation TERMINEE (RF06, RG30). Un seul avis par réservation, non modifiable. */
class AvisService
{
    /**
     * Statut et existence sont vérifiés d'abord pour donner un message clair ; la garantie d'unicité reste l'index unique
     * de la base : deux dépôts simultanés ne produisent qu'un avis, l'autre reçoit le même 409 que le doublon séquentiel.
     * Le client et le prestataire de l'avis viennent TOUJOURS de la réservation, jamais de la requête.
     *
     * @throws ReservationImpossibleException 404 (introuvable ou à quelqu'un d'autre) ou 409 (pas terminée, déjà noté)
     */
    public function deposer(User $client, string $reservationId, int $note, ?string $commentaire): Avis
    {
        $reservation = Str::isUuid($reservationId)
            ? Reservation::query()->with('creneau')->where('client_id', $client->id)->find($reservationId)
            : null;

        if ($reservation === null) {
            throw new ReservationImpossibleException('Réservation introuvable.', Response::HTTP_NOT_FOUND);
        }

        if ($reservation->statut !== StatutReservation::Terminee) {
            throw new ReservationImpossibleException(self::messagePasTerminee($reservation->statut), Response::HTTP_CONFLICT);
        }

        try {
            $avis = DB::transaction(function () use ($reservation, $client, $note, $commentaire) {
                $avis = new Avis;
                $avis->reservation_id = $reservation->id;
                $avis->client_id = $client->id;
                $avis->prestataire_id = $reservation->creneau->prestataire_id;
                $avis->note = $note;
                $avis->commentaire = $commentaire;
                $avis->save();

                return $avis;
            });
        } catch (UniqueConstraintViolationException) {
            throw self::dejaNote();
        }

        AvisDepose::dispatch($avis);

        return $avis;
    }

    public static function dejaNote(): ReservationImpossibleException
    {
        return new ReservationImpossibleException('Vous avez déjà donné votre avis pour cette réservation.', Response::HTTP_CONFLICT);
    }

    private static function messagePasTerminee(StatutReservation $statut): string
    {
        return match ($statut) {
            StatutReservation::EnAttente => 'Vous pourrez donner votre avis une fois la prestation terminée : cette réservation attend encore la réponse du prestataire.',
            StatutReservation::Acceptee => 'Vous pourrez donner votre avis une fois la prestation terminée : cette réservation attend encore votre paiement.',
            StatutReservation::Confirmee => 'Vous pourrez donner votre avis une fois la prestation terminée : cette réservation n\'a pas encore eu lieu.',
            StatutReservation::EnCours => 'Vous pourrez donner votre avis une fois la prestation terminée : elle est encore en cours.',
            StatutReservation::Refusee => 'Vous ne pouvez pas donner votre avis : cette réservation a été refusée.',
            StatutReservation::Annulee => 'Vous ne pouvez pas donner votre avis : cette réservation a été annulée.',
            StatutReservation::Terminee => 'Cette réservation est terminée.',
        };
    }
}
