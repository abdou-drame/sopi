<?php

namespace App\Services;

use App\Enums\StatutCompte;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationCreee;
use App\Exceptions\ReservationImpossibleException;
use App\Models\Creneau;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Réservation d'un créneau par un client (RF03, RF04, RG08, RG09). */
class ReservationService
{
    /**
     * Crée une réservation EN_ATTENTE sur un créneau que le client a verrouillé, et passe le créneau à « réservé ».
     * L'atomicité est portée par Creneau::marquerReserve() (une seule mise à jour conditionnelle), dans la même transaction
     * que l'insertion : si l'insertion échoue, le créneau retrouve son état d'origine.
     *
     * @throws ReservationImpossibleException 404 (créneau introuvable) ou 409 (verrou expiré ou créneau pris)
     * @throws ValidationException 422 (prestation non proposée sur ce créneau)
     */
    public function creer(User $client, string $creneauId, string $prestationId): Reservation
    {
        $creneau = $this->creneauVisible($creneauId);
        if ($creneau === null) {
            throw new ReservationImpossibleException('Créneau introuvable.', Response::HTTP_NOT_FOUND);
        }

        $prestation = Prestation::query()
            ->where('prestataire_id', $creneau->prestataire_id)
            ->where('actif', true)
            ->find($prestationId);
        if ($prestation === null) {
            throw ValidationException::withMessages(['prestation_id' => ['Cette prestation n\'est pas proposée sur ce créneau.']]);
        }

        try {
            $reservation = DB::transaction(function () use ($client, $creneau, $prestation) {
                if (! $creneau->marquerReserve($client->id)) {
                    throw $this->refus($creneau, $client);
                }

                $reservation = new Reservation;
                $reservation->forceFill([
                    'client_id' => $client->id,
                    'creneau_id' => $creneau->id,
                    'prestation_id' => $prestation->id,
                    // Prix convenu, figé ici (jamais fourni par le client) : relu dans la transaction, à l'insertion.
                    'prix_applique' => Prestation::query()->whereKey($prestation->id)->value('prix'),
                    'statut' => StatutReservation::EnAttente,
                ])->save();

                return $reservation;
            });
        } catch (UniqueConstraintViolationException) {
            // Une réservation active existe déjà sur ce créneau : la transaction est annulée, le créneau reste intact.
            throw new ReservationImpossibleException('Ce créneau n\'est plus disponible.', Response::HTTP_CONFLICT);
        }

        ReservationCreee::dispatch($reservation);

        return $reservation->setRelation('creneau', $creneau->refresh())->setRelation('prestation', $prestation->load('prestataire'));
    }

    /** Créneau à venir d'un prestataire dont le compte est actif, ou null. */
    private function creneauVisible(string $id): ?Creneau
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return Creneau::query()
            ->aVenir()
            ->whereHas('prestataire.user', fn (Builder $q) => $q->where('statut', StatutCompte::Actif->value))
            ->find($id);
    }

    /**
     * Explique l'échec de marquerReserve (lecture APRÈS l'écriture refusée, jamais pour décider du verrou).
     * « Délai dépassé » (mémoire, tableau 2.6, 3b) quand c'est le verrou de ce client qui a expiré, qu'il soit encore posé
     * ou déjà levé par le balayage de la minute ; « plus disponible » quand un autre client a pris le créneau.
     */
    private function refus(Creneau $creneau, User $client): ReservationImpossibleException
    {
        $actuel = Creneau::query()->find($creneau->id);

        $verrouExpire = $actuel?->verrouExpireDuClient($client->id) ?? false;

        return $verrouExpire
            ? new ReservationImpossibleException('Délai dépassé. Veuillez sélectionner de nouveau un créneau.', Response::HTTP_CONFLICT)
            : new ReservationImpossibleException('Ce créneau n\'est plus disponible.', Response::HTTP_CONFLICT);
    }
}
