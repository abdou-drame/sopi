<?php

namespace App\Services;

use App\Enums\CauseAnnulation;
use App\Enums\Role;
use App\Enums\StatutCreneau;
use App\Enums\StatutReservation;
use App\Events\Reservation\ReservationAnnulee;
use App\Exceptions\ReservationImpossibleException;
use App\Models\Prestataire;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Indisponibilité d'un prestataire (RF02, RF12, RG26) : toute la plage est traitée dans UNE transaction.
 * Les créneaux libres et les verrous passent à INDISPONIBLE ; les réservations EN_ATTENTE, ACCEPTEE ou CONFIRMEE sont
 * annulées (par le prestataire) et leur créneau passe à INDISPONIBLE ; une prestation EN_COURS refuse tout.
 * Un créneau réservé sans réservation active (prestation TERMINEE, par exemple) reste inchangé, comme avant M5.7.
 * Remboursement (M6) et notifications (M10) viendront plus tard : seuls des événements sont publiés.
 */
class IndisponibiliteService
{
    public const DUREE_MAX_JOURS = 90;

    /**
     * @return array{bloques: int, reserves: int, verrouilles: int, reservationsAnnulees: int}
     *                                                                                         `bloques` = libres (verrous expirés compris), `verrouilles` = verrous valides supprimés, `reserves` = créneaux réservés rendus indisponibles
     *
     * @throws ReservationImpossibleException 409 si une prestation est en cours ou si la situation a changé en cours de route
     */
    public function declarer(Prestataire $prestataire, Carbon $debut, Carbon $fin): array
    {
        $dansLaPlage = fn () => $prestataire->creneaux()
            ->where('date_debut', '<', $fin)
            ->where('date_fin', '>', $debut);

        $evenements = [];

        $bilan = DB::transaction(function () use ($prestataire, $debut, $fin, $dansLaPlage, &$evenements) {
            // Réservations actives des créneaux réservés de la plage : simple liste de pistes, chaque écriture est conditionnelle.
            $reservations = Reservation::query()
                ->whereIn('statut', [...array_column(StatutReservation::ANNULABLES_PAR_CLIENT, 'value'), StatutReservation::EnCours->value])
                ->whereHas('creneau', fn (Builder $q) => $q
                    ->where('prestataire_id', $prestataire->id)
                    ->where('statut', StatutCreneau::Reserve->value)
                    ->where('date_debut', '<', $fin)
                    ->where('date_fin', '>', $debut))
                ->with('creneau')
                ->get();

            if ($reservations->contains(fn (Reservation $r) => $r->statut === StatutReservation::EnCours)) {
                throw new ReservationImpossibleException('Une prestation est en cours dans cette plage : l\'indisponibilité ne peut pas être déclarée.', Response::HTTP_CONFLICT);
            }

            foreach ($reservations as $reservation) {
                if (! $reservation->annulerPourIndisponibilite() || ! $reservation->creneau->bloquerSiReserve()) {
                    throw new ReservationImpossibleException('La situation a changé, veuillez réessayer.', Response::HTTP_CONFLICT);
                }
                $evenements[] = new ReservationAnnulee($reservation, Role::Prestataire, CauseAnnulation::Indisponibilite);
            }

            // Libres, verrous expirés et verrous valides : une seule requête conditionnelle (le comptage des verrous valides est informatif).
            $verrouilles = $dansLaPlage()->where('statut', StatutCreneau::Verrouille->value)->where('verrou_expire_a', '>', now())->count();
            $traites = $dansLaPlage()
                ->whereIn('statut', [StatutCreneau::Libre->value, StatutCreneau::Verrouille->value])
                ->update(['statut' => StatutCreneau::Indisponible->value, 'verrou_expire_a' => null, 'verrouille_par' => null]);

            return [
                'bloques' => max(0, $traites - $verrouilles),
                'reserves' => $reservations->count(),
                'verrouilles' => $verrouilles,
                'reservationsAnnulees' => $reservations->count(),
            ];
        });

        // Publiés seulement une fois la transaction validée : un échec n'en laisse aucun.
        foreach ($evenements as $evenement) {
            event($evenement);
        }

        return $bilan;
    }
}
