<?php

namespace App\Enums;

use App\Models\Creneau;
use Illuminate\Database\Eloquent\Builder;

/**
 * Onglets des listes de réservations (client et prestataire) : regroupements de statuts, avec leur propre ordre.
 *
 * - a_venir : EN_ATTENTE, ACCEPTEE (en attente de paiement), CONFIRMEE, EN_COURS dont le créneau n'est pas terminé,
 *   par date de créneau croissante ;
 * - terminees : TERMINEE, les plus récentes d'abord (date du créneau) ;
 * - annulees : ANNULEE et REFUSEE, les plus récentes d'abord (date du créneau).
 */
enum GroupeReservation: string
{
    case AVenir = 'a_venir';
    case Terminees = 'terminees';
    case Annulees = 'annulees';

    /** @return list<StatutReservation> */
    public function statuts(): array
    {
        return match ($this) {
            self::AVenir => [StatutReservation::EnAttente, StatutReservation::Acceptee, StatutReservation::Confirmee, StatutReservation::EnCours],
            self::Terminees => [StatutReservation::Terminee],
            self::Annulees => [StatutReservation::Annulee, StatutReservation::Refusee],
        };
    }

    /** Restreint la requête (sur reservations) aux statuts du groupe et applique son tri, stable grâce à l'identifiant. */
    public function appliquer(Builder $requete): Builder
    {
        $requete->whereIn('reservations.statut', array_map(fn (StatutReservation $s) => $s->value, $this->statuts()));

        if ($this === self::AVenir) {
            // Créneau pas encore terminé (une prestation en cours reste « à venir » jusqu'à la fin de son créneau).
            $requete->whereHas('creneau', fn (Builder $c) => $c->where('date_fin', '>=', now()));
        }

        $dateCreneau = Creneau::query()->select('date_debut')->whereColumn('creneaux.id', 'reservations.creneau_id');

        return $requete
            ->orderBy($dateCreneau, $this === self::AVenir ? 'asc' : 'desc')
            ->orderBy('reservations.id');
    }
}
