<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutLitige;
use App\Enums\StatutReservation;
use App\Models\Prestataire;
use App\Models\Sanction;
use App\Models\User;
use App\Support\RechercheTexte;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as RequeteBase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Consultation des comptes clients et prestataires par l'administrateur (M12.1) : lecture seule. Les administrateurs ne sont
 * jamais renvoyés. Aucune requête par ligne : la liste charge les profils prestataires en une requête, la fiche fait un nombre
 * fixe de requêtes d'agrégat, quel que soit le volume.
 */
class UtilisateursAdminService
{
    public const PAR_PAGE = 20;

    /**
     * @param  array{role?: ?string, statut?: ?string, q?: ?string, debut?: ?Carbon, fin?: ?Carbon}  $filtres  `fin` : borne haute exclue
     */
    public function lister(array $filtres): LengthAwarePaginator
    {
        $requete = User::query()
            ->whereIn('users.role', [Role::Client->value, Role::Prestataire->value])
            ->with('prestataire:id,user_id,metier,quartier,nom_entreprise');

        if (filled($filtres['role'] ?? null)) {
            $requete->where('users.role', $filtres['role']);
        }
        if (filled($filtres['statut'] ?? null)) {
            $requete->where('users.statut', $filtres['statut']);
        }
        if (filled($filtres['debut'] ?? null)) {
            $requete->where('users.created_at', '>=', $filtres['debut']->copy()->utc());
        }
        if (filled($filtres['fin'] ?? null)) {
            $requete->where('users.created_at', '<', $filtres['fin']->copy()->utc());
        }
        if (filled($filtres['q'] ?? null)) {
            $this->rechercher($requete, $filtres['q']);
        }

        return $requete->orderByDesc('users.created_at')->orderByDesc('users.id')->paginate(self::PAR_PAGE);
    }

    /** Compte client ou prestataire, ou null (identifiant mal formé, inconnu, ou compte administrateur). */
    public function trouver(string $id): ?User
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return User::query()
            ->whereIn('role', [Role::Client->value, Role::Prestataire->value])
            ->with('prestataire')
            ->find($id);
    }

    /**
     * Éléments de la fiche : sanctions, puis les chiffres propres au rôle. Le profil prestataire doit être chargé.
     *
     * @return array<string, mixed>
     */
    public function synthese(User $utilisateur): array
    {
        $sanctions = Sanction::query()->where('user_id', $utilisateur->id)->get(['id', 'user_id', 'type', 'date_fin', 'levee_le']);
        // Au plus un blocage actif par compte (index unique) ; à défaut, un avertissement non levé.
        $active = $sanctions->whereNull('levee_le')->sortByDesc(fn (Sanction $s) => $s->type->bloque())->first();

        $synthese = [
            'sanctionActive' => $active === null ? null : [
                'id' => $active->id,
                'type' => $active->type->value,
                'dateFin' => $active->date_fin?->toIso8601String(),
            ],
            'nombreSanctions' => $sanctions->count(),
        ];

        return $utilisateur->role === Role::Prestataire && $utilisateur->prestataire !== null
            ? $synthese + ['prestataire' => $this->synthesePrestataire($utilisateur->prestataire)]
            : $synthese + ['client' => $this->syntheseClient($utilisateur)];
    }

    private function syntheseClient(User $client): array
    {
        return [
            'reservationsParStatut' => $this->parStatut(DB::table('reservations')->where('client_id', $client->id)),
            'nombreAvisDonnes' => DB::table('avis')->where('client_id', $client->id)->count(),
            'litiges' => $this->litiges(DB::table('litiges')->where('client_id', $client->id)),
        ];
    }

    private function synthesePrestataire(Prestataire $prestataire): array
    {
        // Avis non masqués seulement, comme la note affichée aux clients.
        $ligne = DB::table('avis')->where('prestataire_id', $prestataire->id)->whereNull('masque_le')->selectRaw('avg(note) as moyenne, count(*) as nombre')->first();
        $avis = ConsultationAvisService::formater($ligne->moyenne, $ligne->nombre);

        return [
            'adresse' => $prestataire->adresse,
            'solde' => (int) round((float) $prestataire->solde),
            'prestationsActives' => DB::table('prestations')->where('prestataire_id', $prestataire->id)->where('actif', true)->count(),
            'reservationsParStatut' => $this->parStatut(
                DB::table('reservations')->join('creneaux', 'creneaux.id', '=', 'reservations.creneau_id')->where('creneaux.prestataire_id', $prestataire->id)
            ),
            'noteMoyenne' => $avis['noteMoyenne'],
            'nombreAvis' => $avis['nombreAvis'],
            'nombreRetraits' => DB::table('retraits')->where('prestataire_id', $prestataire->id)->count(),
            'litiges' => $this->litiges(DB::table('litiges')->where('prestataire_id', $prestataire->id)),
        ];
    }

    /** Une requête : réservations de la sélection par statut, tous les statuts présents (à zéro si besoin). */
    private function parStatut(RequeteBase $requete): array
    {
        $comptes = $requete->selectRaw('reservations.statut as statut, count(*) as nombre')->groupBy('reservations.statut')->pluck('nombre', 'statut');

        $resultat = [];
        foreach (StatutReservation::cases() as $statut) {
            $resultat[$statut->value] = (int) ($comptes[$statut->value] ?? 0);
        }

        return $resultat;
    }

    /** @return array{total: int, ouverts: int} */
    private function litiges(RequeteBase $requete): array
    {
        $ligne = $requete->selectRaw('count(*) as total, coalesce(sum(case when statut = ? then 1 else 0 end), 0) as ouverts', [StatutLitige::Ouvert->value])->first();

        return ['total' => (int) $ligne->total, 'ouverts' => (int) $ligne->ouverts];
    }

    /** Nom, prénom, « prénom nom », « nom prénom », e-mail, téléphone ou nom d'entreprise contenant le texte saisi. */
    private function rechercher(Builder $requete, string $texte): void
    {
        $motif = RechercheTexte::motif($texte);

        $requete->where(function (Builder $q) use ($motif) {
            foreach (['users.nom', 'users.prenom', "(users.prenom || ' ' || users.nom)", "(users.nom || ' ' || users.prenom)", 'users.email', 'users.telephone'] as $colonne) {
                $q->orWhereRaw(RechercheTexte::clause($colonne), [$motif]);
            }
            $q->orWhereHas('prestataire', fn (Builder $p) => $p->whereRaw(RechercheTexte::clause('prestataires.nom_entreprise'), [$motif]));
        });
    }
}
