<?php

namespace App\Services;

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Support\RechercheTexte;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Consultation des réservations par l'administrateur (M12.2) : lecture seule. Le code de réservation n'est jamais chargé dans
 * les données renvoyées (il peut seulement servir de critère de recherche). Nombre de requêtes constant : tout est chargé par
 * relations (une requête par relation, quel que soit le nombre de lignes).
 */
class ReservationsAdminService
{
    public const PAR_PAGE = 20;

    /** Colonnes de la réservation : jamais code_reservation. */
    private const COLONNES = ['reservations.id', 'client_id', 'creneau_id', 'prestation_id', 'prix_applique', 'statut', 'motif_refus', 'acceptee_le', 'reservations.created_at', 'reservations.updated_at'];

    /** Colonnes du paiement : jamais reference_dexpay ni url_paiement. */
    private const COLONNES_PAIEMENT = ['id', 'reservation_id', 'statut', 'montant_acompte', 'commission', 'paye_le', 'rembourse_le', 'remboursement_du_le', 'remboursement_tentatives', 'remboursement_derniere_erreur', 'credite_le', 'created_at'];

    /**
     * @param  array{statut?: ?string, prestataire?: ?string, client?: ?string, q?: ?string, debut?: ?Carbon, fin?: ?Carbon}  $filtres  `fin` : borne haute exclue
     */
    public function lister(array $filtres): LengthAwarePaginator
    {
        $requete = Reservation::query()
            ->select(self::COLONNES)
            ->with([
                'client:id,prenom,nom',
                'creneau:id,prestataire_id,date_debut,date_fin',
                'creneau.prestataire:id,nom_entreprise',
                'prestation:id,nom',
                'paiements:id,reservation_id,statut,created_at',
            ]);

        if (filled($filtres['statut'] ?? null)) {
            $requete->where('reservations.statut', $filtres['statut']);
        }
        // Client et prestataire : identifiant, ou nom (recherche partielle).
        if (filled($filtres['client'] ?? null)) {
            Str::isUuid($filtres['client'])
                ? $requete->where('reservations.client_id', $filtres['client'])
                : $requete->whereHas('client', fn (Builder $q) => $q->whereRaw(RechercheTexte::clause("coalesce(prenom, '') || ' ' || coalesce(nom, '')"), [RechercheTexte::motif($filtres['client'])]));
        }
        if (filled($filtres['prestataire'] ?? null)) {
            $requete->whereHas('creneau', fn (Builder $q) => Str::isUuid($filtres['prestataire'])
                ? $q->where('prestataire_id', $filtres['prestataire'])
                : $q->whereHas('prestataire', fn (Builder $p) => $p->whereRaw(RechercheTexte::clause('nom_entreprise'), [RechercheTexte::motif($filtres['prestataire'])])));
        }
        if (filled($filtres['debut'] ?? null) || filled($filtres['fin'] ?? null)) {
            $requete->whereHas('creneau', function (Builder $q) use ($filtres) {
                if (filled($filtres['debut'] ?? null)) {
                    $q->where('date_debut', '>=', $filtres['debut']->copy()->utc());
                }
                if (filled($filtres['fin'] ?? null)) {
                    $q->where('date_debut', '<', $filtres['fin']->copy()->utc());
                }
            });
        }
        if (filled($filtres['q'] ?? null)) {
            $requete->whereRaw(RechercheTexte::clause('reservations.code_reservation'), [RechercheTexte::motif($filtres['q'])]);
        }

        return $requete->orderByDesc('reservations.created_at')->orderByDesc('reservations.id')->paginate(self::PAR_PAGE);
    }

    /** Réservation avec tout ce qu'il faut au détail, ou null (identifiant mal formé ou inconnu). */
    public function trouver(string $id): ?Reservation
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return Reservation::query()
            ->select(self::COLONNES)
            ->with([
                'client:id,prenom,nom',
                'creneau:id,prestataire_id,date_debut,date_fin',
                'creneau.prestataire:id,user_id,nom_entreprise',
                'prestation:id,nom,duree_minutes',
                'paiements' => fn ($q) => $q->select(self::COLONNES_PAIEMENT),
                'avis:id,reservation_id,note,signale_le,modere_le,masque_le,created_at',
                'litiges:id,reservation_id,statut,created_at',
            ])
            ->find($id);
    }

    /**
     * Le paiement qui représente la réservation : réussi, puis en attente, puis remboursé, puis échoué ; à égalité le plus récent.
     * (Il n'y a au plus qu'un paiement réussi ou en attente par réservation, garanti par la base.)
     */
    public static function paiementRetenu(Collection $paiements): ?Paiement
    {
        $priorite = [StatutPaiement::Reussi->value => 0, StatutPaiement::EnAttente->value => 1, StatutPaiement::Rembourse->value => 2, StatutPaiement::Echoue->value => 3];

        return $paiements
            ->sortBy([
                fn (Paiement $a, Paiement $b) => $priorite[$a->statut->value] <=> $priorite[$b->statut->value],
                fn (Paiement $a, Paiement $b) => $b->created_at <=> $a->created_at,
            ])
            ->first();
    }
}
