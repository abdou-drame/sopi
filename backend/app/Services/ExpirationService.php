<?php

namespace App\Services;

use App\Enums\CauseExpiration;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationExpiree;
use App\Models\Creneau;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Expirations automatiques (RG09, RG10, RG11), appelées chaque minute par le scheduler. Chaque réservation est traitée
 * dans sa propre transaction : une erreur est journalisée (identifiant et type d'erreur seulement) et n'arrête pas les
 * autres. La sélection des candidats n'est qu'une liste de pistes : la décision est l'UPDATE conditionnel.
 */
class ExpirationService
{
    public const TAILLE_LOT = 100;

    /** Annule les demandes EN_ATTENTE sans réponse du prestataire depuis plus de 12 h. Renvoie le nombre annulé. */
    public function expirerSansReponse(): int
    {
        $limite = now()->subHours((int) config('sopi.reservations.delai_reponse_heures'));

        return $this->expirer(
            StatutReservation::EnAttente, 'created_at', $limite, CauseExpiration::SansReponse,
            fn (Reservation $r) => $r->expirerSiSansReponse($limite),
        );
    }

    /** Annule les réservations ACCEPTEE non payées depuis plus de 2 h après acceptee_le. Renvoie le nombre annulé. */
    public function expirerImpayees(): int
    {
        $limite = now()->subHours((int) config('sopi.reservations.delai_paiement_heures'));

        return $this->expirer(
            StatutReservation::Acceptee, 'acceptee_le', $limite, CauseExpiration::Impayee,
            fn (Reservation $r) => $r->expirerSiImpayee($limite),
        );
    }

    /** Libère les créneaux dont le verrou a expiré. Aucun événement. Renvoie le nombre libéré. */
    public function libererVerrousExpires(): int
    {
        return Creneau::libererVerrousExpires();
    }

    /** @param callable(Reservation): bool $annuler UPDATE conditionnel ; faux si la réservation a changé entre-temps */
    private function expirer(StatutReservation $statut, string $colonne, CarbonInterface $limite, CauseExpiration $cause, callable $annuler): int
    {
        $traitees = 0;

        Reservation::query()
            ->where('statut', $statut->value)
            ->where($colonne, '<=', $limite)
            ->chunkById(self::TAILLE_LOT, function ($lot) use ($annuler, $cause, &$traitees) {
                foreach ($lot as $reservation) {
                    try {
                        $annulee = DB::transaction(function () use ($reservation, $annuler) {
                            if (! $annuler($reservation)) {
                                return false;
                            }
                            // Si le créneau n'est plus lié/réservé (incohérence), la réservation reste annulée : rien à libérer.
                            $reservation->creneau->libererPourReservation($reservation);

                            return true;
                        });
                    } catch (Throwable $e) {
                        Log::error('Expiration de réservation échouée', ['reservation_id' => $reservation->id, 'erreur' => $e::class]);

                        continue;
                    }

                    if ($annulee) {
                        $traitees++;
                        ReservationExpiree::dispatch($reservation, $cause);
                    }
                }
            });

        return $traitees;
    }
}
