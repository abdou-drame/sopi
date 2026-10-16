<?php

namespace App\Services;

use App\Enums\CauseAnnulation;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Enums\TypeSanction;
use App\Events\Reservation\ReservationAnnulee;
use App\Events\SanctionLevee;
use App\Events\SanctionPosee;
use App\Exceptions\ReservationImpossibleException;
use App\Models\Avis;
use App\Models\Litige;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\Sanction;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanctions disciplinaires (RF19) : avertissement, suspension temporaire, bannissement définitif.
 *
 * Poser une sanction de blocage est « tout ou rien » : UNE transaction écrit la sanction, change le statut du compte et révoque
 * TOUS ses jetons Sanctum. Pour un prestataire, ses réservations EN_ATTENTE, ACCEPTEE ou CONFIRMEE sont annulées comme pour une
 * indisponibilité (créneau rendu indisponible, paiement REUSSI marqué « remboursement dû » dans la transaction puis remboursé
 * après elle ; la commande planifiée reprend les échecs). Pour un client, ses réservations EN_ATTENTE et ACCEPTEE sont annulées
 * et leurs créneaux libérés (aucun paiement n'existe encore). Une prestation EN_COURS va à son terme ; solde et retraits ne sont pas
 * touchés par le service (les routes prestataire restent fermées par la révocation des jetons). Les événements partent après validation.
 */
class SanctionService
{
    public function __construct(private readonly RemboursementPaiementService $remboursements) {}

    /**
     * @param  Closure(): void|null  $dansLaTransaction  Exécutée dans la même transaction, avant l'écriture de la sanction (clôture du litige).
     *
     * @throws ReservationImpossibleException 403 (administrateur), 404 (litige ou avis inconnu), 409 (compte inactif, sanction déjà active ou déjà posée), 422 (lien incohérent)
     */
    public function poser(User $admin, User $cible, TypeSanction $type, string $motif, ?Carbon $dateFin = null, ?Litige $litige = null, ?Avis $avis = null, ?Closure $dansLaTransaction = null): Sanction
    {
        if ($cible->role === Role::Administrateur) {
            throw new ReservationImpossibleException('Un administrateur ne peut pas être sanctionné.', Response::HTTP_FORBIDDEN);
        }

        $this->verifierLiens($cible, $litige, $avis);

        $evenements = [];
        $aRembourser = [];

        try {
            $sanction = DB::transaction(function () use ($admin, $cible, $type, $motif, $dateFin, $litige, $avis, $dansLaTransaction, &$evenements, &$aRembourser) {
                if ($dansLaTransaction !== null) {
                    $dansLaTransaction();
                }

                if ($type->bloque()) {
                    $this->bloquerLeCompte($cible, $type);
                    $this->revoquerLesJetons($cible);
                    $this->annulerLesReservations($cible, $evenements, $aRembourser);
                } else {
                    $this->refuserDoublonAvertissement($cible, $motif, $litige, $avis);
                }

                $sanction = new Sanction;
                $sanction->user_id = $cible->id;
                $sanction->type = $type;
                $sanction->motif = $motif;
                $sanction->date_fin = $type === TypeSanction::SuspensionTemporaire ? $dateFin : null;
                $sanction->litige_id = $litige?->id;
                $sanction->avis_id = $avis?->id;
                $sanction->pose_par_id = $admin->id;
                $sanction->save();

                $evenements[] = new SanctionPosee($sanction);

                return $sanction;
            });
        } catch (UniqueConstraintViolationException) {
            // Deux poses simultanées : les index uniques (un blocage actif par compte, une sanction par litige) n'en laissent passer qu'une.
            throw new ReservationImpossibleException($type->bloque() ? 'Ce compte fait déjà l\'objet d\'une sanction active.' : 'Ce litige a déjà donné lieu à une sanction.', Response::HTTP_CONFLICT);
        }

        foreach ($evenements as $evenement) {
            event($evenement);
        }

        // Hors transaction : un échec n'annule pas la sanction, la commande planifiée reprendra le remboursement.
        $this->remboursements->tenterTous($aRembourser);

        return $sanction;
    }

    /**
     * Lève une sanction active à la main. Mise à jour conditionnelle (levee_le vide) : deux levées simultanées, une seule passe.
     * Le compte redevient actif seulement s'il portait le statut de cette sanction.
     *
     * @throws ReservationImpossibleException 404 (inconnue), 409 (déjà levée)
     */
    public function lever(User $admin, string $id): Sanction
    {
        $sanction = Str::isUuid($id) ? Sanction::query()->find($id) : null;

        if ($sanction === null) {
            throw new ReservationImpossibleException('Sanction introuvable.', Response::HTTP_NOT_FOUND);
        }

        $sanction = DB::transaction(function () use ($admin, $sanction) {
            if (! $this->marquerLevee($sanction, $admin)) {
                throw new ReservationImpossibleException('Cette sanction est déjà levée.', Response::HTTP_CONFLICT);
            }

            $this->restaurerLeCompte($sanction);
            event(new SanctionLevee($sanction->refresh()));

            return $sanction;
        });

        return $sanction->load(['posePar', 'leveePar']);
    }

    /** Lève les suspensions arrivées à échéance. Renvoie le nombre de sanctions levées. */
    public function leverExpirees(): int
    {
        $levees = 0;

        Sanction::query()
            ->whereNull('levee_le')
            ->where('type', TypeSanction::SuspensionTemporaire->value)
            ->where('date_fin', '<=', now())
            ->orderBy('date_fin')
            ->each(function (Sanction $sanction) use (&$levees) {
                DB::transaction(function () use ($sanction, &$levees) {
                    if ($this->marquerLevee($sanction, null)) {
                        $this->restaurerLeCompte($sanction);
                        event(new SanctionLevee($sanction->refresh()));
                        $levees++;
                    }
                });
            });

        return $levees;
    }

    /** Historique d'un compte, de la plus récente à la plus ancienne. */
    public function historique(User $utilisateur): Collection
    {
        return Sanction::query()->with(['posePar', 'leveePar'])
            ->where('user_id', $utilisateur->id)->latest()->latest('id')->get();
    }

    public function trouverUtilisateur(string $id): ?User
    {
        return Str::isUuid($id) ? User::query()->find($id) : null;
    }

    /**
     * Message de refus de connexion d'un compte non actif. Pour un compte suspendu ou banni par sanction, il précise le motif et,
     * pour une suspension, la date de fin ; sans sanction active (statut posé autrement), le message générique du statut.
     */
    public function messageRefusConnexion(User $utilisateur): string
    {
        $generique = $utilisateur->statut->messageRefus();

        if (! in_array($utilisateur->statut, [StatutCompte::Suspendu, StatutCompte::Banni], true)) {
            return $generique;
        }

        $sanction = Sanction::query()->bloquantesActives()->where('user_id', $utilisateur->id)->latest()->first();
        if ($sanction === null) {
            return $generique;
        }

        $base = $sanction->type === TypeSanction::SuspensionTemporaire
            ? 'Votre compte est suspendu jusqu\'au '.$sanction->date_fin->timezone(config('app.timezone'))->format('d/m/Y \à H:i').'.'
            : 'Votre compte a été banni.';

        return $base.' Motif : '.$sanction->motif;
    }

    /**
     * Champs structurés ajoutés à la réponse 403 de connexion : « motif » (sanction active, ou motif du refus d'inscription) et,
     * pour une suspension temporaire, « finSuspension » (ISO 8601). Un champ sans valeur connue est absent.
     *
     * @return array{motif?: string, finSuspension?: string}
     */
    public function detailRefusConnexion(User $utilisateur): array
    {
        if ($utilisateur->statut === StatutCompte::Refuse) {
            $motif = $utilisateur->prestataire?->motif_refus;

            return $motif === null ? [] : ['motif' => $motif];
        }

        if (! in_array($utilisateur->statut, [StatutCompte::Suspendu, StatutCompte::Banni], true)) {
            return [];
        }

        $sanction = Sanction::query()->bloquantesActives()->where('user_id', $utilisateur->id)->latest()->first();
        if ($sanction === null) {
            return [];
        }

        $detail = ['motif' => $sanction->motif];
        if ($sanction->type === TypeSanction::SuspensionTemporaire && $sanction->date_fin !== null) {
            $detail['finSuspension'] = $sanction->date_fin->toIso8601String();
        }

        return $detail;
    }

    /** @throws ReservationImpossibleException */
    private function verifierLiens(User $cible, ?Litige $litige, ?Avis $avis): void
    {
        if ($litige !== null) {
            $litige->loadMissing('prestataire');
            if (! in_array($cible->id, [$litige->client_id, $litige->prestataire->user_id], true)) {
                throw new ReservationImpossibleException('Ce compte n\'est pas une partie de ce litige.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($avis !== null && $avis->client_id !== $cible->id) {
            throw new ReservationImpossibleException('Ce compte n\'est pas l\'auteur de cet avis.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Passe le compte de ACTIF au statut de la sanction, en une mise à jour conditionnelle.
     *
     * @throws ReservationImpossibleException 409 si le compte n'est pas actif (déjà sanctionné, en attente, refusé)
     */
    private function bloquerLeCompte(User $cible, TypeSanction $type): void
    {
        $modifies = User::query()
            ->whereKey($cible->id)
            ->where('statut', StatutCompte::Actif->value)
            ->update(['statut' => $type->statutCompte()->value, 'updated_at' => now()]);

        if ($modifies === 0) {
            $sanctionne = in_array($cible->fresh()->statut, [StatutCompte::Suspendu, StatutCompte::Banni], true);

            throw new ReservationImpossibleException($sanctionne ? 'Ce compte fait déjà l\'objet d\'une sanction active.' : 'Ce compte n\'est pas actif : il ne peut pas être suspendu ou banni.', Response::HTTP_CONFLICT);
        }

        $cible->refresh();
    }

    private function revoquerLesJetons(User $cible): void
    {
        $cible->tokens()->delete();
    }

    /**
     * Un avertissement identique (même compte, même motif, même litige ou avis) posé il y a moins d'une minute est un doublon
     * (double envoi) : refusé.
     */
    private function refuserDoublonAvertissement(User $cible, string $motif, ?Litige $litige, ?Avis $avis): void
    {
        $doublon = Sanction::query()
            ->where('user_id', $cible->id)
            ->where('type', TypeSanction::Avertissement->value)
            ->where('motif', $motif)
            ->where('litige_id', $litige?->id)
            ->where('avis_id', $avis?->id)
            ->where('created_at', '>=', now()->subMinute())
            ->exists();

        if ($doublon) {
            throw new ReservationImpossibleException('Cet avertissement vient déjà d\'être enregistré.', Response::HTTP_CONFLICT);
        }
    }

    /**
     * Annule les réservations à venir du compte sanctionné. Chaque écriture est conditionnelle ; tout paiement REUSSI d'une
     * réservation annulée est marqué « remboursement dû » (remboursement intégral).
     *
     * @param  list<ReservationAnnulee>  $evenements
     * @param  list<Paiement>  $aRembourser
     */
    private function annulerLesReservations(User $cible, array &$evenements, array &$aRembourser): void
    {
        $prestataire = $cible->role === Role::Prestataire ? $cible->prestataire : null;

        $requete = Reservation::query()->with('creneau');
        if ($prestataire !== null) {
            $requete->whereIn('statut', array_column(StatutReservation::ANNULABLES_PAR_CLIENT, 'value'))
                ->whereHas('creneau', fn (Builder $q) => $q->where('prestataire_id', $prestataire->id));
        } else {
            $requete->where('client_id', $cible->id)
                ->whereIn('statut', [StatutReservation::EnAttente->value, StatutReservation::Acceptee->value]);
        }

        foreach ($requete->get() as $reservation) {
            if (! $reservation->annulerPourIndisponibilite()) {
                continue;
            }

            // Prestataire sanctionné : le créneau n'est plus réservable. Client sanctionné : le créneau redevient libre.
            $prestataire !== null ? $reservation->creneau->bloquerSiReserve() : $reservation->creneau->libererPourReservation($reservation);

            foreach ($reservation->paiements()->where('statut', StatutPaiement::Reussi->value)->get() as $paiement) {
                $this->remboursements->marquerDu($paiement);
                $aRembourser[] = $paiement;
            }

            $evenements[] = new ReservationAnnulee($reservation, Role::Administrateur, CauseAnnulation::Sanction);
        }
    }

    private function marquerLevee(Sanction $sanction, ?User $admin): bool
    {
        return Sanction::query()
            ->whereKey($sanction->id)
            ->whereNull('levee_le')
            ->update(['levee_le' => now(), 'levee_par_id' => $admin?->id, 'updated_at' => now()]) === 1;
    }

    /**
     * Rend au compte le statut ACTIF s'il porte celui de la sanction levée et qu'aucune autre sanction de blocage n'est active
     * (l'index unique l'interdit, la vérification est une sécurité).
     */
    private function restaurerLeCompte(Sanction $sanction): void
    {
        $statut = $sanction->type->statutCompte();
        if ($statut === null) {
            return;
        }

        $autre = Sanction::query()->bloquantesActives()->where('user_id', $sanction->user_id)->whereKeyNot($sanction->id)->exists();
        if ($autre) {
            return;
        }

        User::query()
            ->whereKey($sanction->user_id)
            ->where('statut', $statut->value)
            ->update(['statut' => StatutCompte::Actif->value, 'updated_at' => now()]);
    }
}
