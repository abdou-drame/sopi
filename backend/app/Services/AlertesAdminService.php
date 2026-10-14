<?php

namespace App\Services;

use App\Enums\StatutCompte;
use App\Enums\StatutEnvoi;
use App\Enums\StatutLitige;
use App\Enums\StatutPaiement;
use App\Enums\StatutRetrait;
use App\Enums\TypeSanction;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alertes du tableau de bord administrateur (M11.3, RG35). Calculées à la demande : rien n'est envoyé (ni e-mail ni push),
 * l'administrateur les lit sur son tableau de bord. Une alerte n'existe que si au moins un élément est concerné (3b : aucun
 * élément en attente, aucune bannière).
 *
 * Une seule requête par type d'alerte : les éléments concernés sont lus du plus ancien au plus récent, limités à `ids_max`,
 * et le total vient d'une fonction de fenêtre (COUNT(*) OVER ()), donc aucune requête par élément. Seuils : sopi.admin.alertes.*
 * Aucune donnée personnelle : des identifiants, des compteurs et des durées.
 */
class AlertesAdminService
{
    public const INFO = 'info';

    public const ATTENTION = 'attention';

    public const CRITIQUE = 'critique';

    /**
     * Ressource d'administration où ouvrir les `ids` de chaque type d'alerte : `/admin/{ressource}` (liste, avec le filtre qui reprend la
     * définition de l'alerte) et, quand elle existe, `/admin/{ressource}/{id}`.
     */
    private const RESSOURCES = [
        'dossiers_en_attente' => 'demandes-prestataires',
        'litiges_ouverts' => 'litiges',
        'avis_signales' => 'avis',
        'retraits_bloques' => 'retraits',
        'remboursements_en_echec' => 'paiements',
        'remboursements_en_retard' => 'paiements',
        'notifications_en_echec' => 'notifications',
        'suspensions_a_echeance' => 'sanctions',
    ];

    private const ORDRE_GRAVITE = [self::CRITIQUE => 0, self::ATTENTION => 1, self::INFO => 2];

    public function alertes(): array
    {
        $maintenant = now();

        $alertes = array_values(array_filter([
            $this->dossiers($maintenant),
            $this->litiges($maintenant),
            $this->avisSignales($maintenant),
            $this->retraitsBloques($maintenant),
            $this->remboursementsEnEchec($maintenant),
            $this->remboursementsEnRetard($maintenant),
            $this->notificationsEnEchec($maintenant),
            $this->suspensionsQuiPrennentFin($maintenant),
        ]));

        // Les plus graves d'abord ; à gravité égale, l'ordre de définition ci-dessus est conservé (tri stable).
        usort($alertes, fn (array $a, array $b) => self::ORDRE_GRAVITE[$a['gravite']] <=> self::ORDRE_GRAVITE[$b['gravite']]);

        $parGravite = array_fill_keys(array_keys(self::ORDRE_GRAVITE), 0);
        foreach ($alertes as $alerte) {
            $parGravite[$alerte['gravite']]++;
        }

        return [
            'dateGeneration' => $maintenant->toIso8601String(),
            'total' => count($alertes),
            'parGravite' => $parGravite,
            'alertes' => $alertes,
        ];
    }

    private function dossiers(Carbon $maintenant): ?array
    {
        $requete = DB::table('prestataires')
            ->join('users', 'users.id', '=', 'prestataires.user_id')
            ->where('users.statut', StatutCompte::EnAttenteValidation->value);

        return $this->parAge($requete, 'prestataires.id', 'prestataires.created_at', $maintenant, 'dossiers_en_attente',
            fn (int $n) => $n === 1 ? '1 dossier de prestataire attend votre validation.' : "$n dossiers de prestataires attendent votre validation.");
    }

    private function litiges(Carbon $maintenant): ?array
    {
        $requete = DB::table('litiges')->where('statut', StatutLitige::Ouvert->value);

        return $this->parAge($requete, 'litiges.id', 'litiges.created_at', $maintenant, 'litiges_ouverts',
            fn (int $n) => $n === 1 ? '1 litige ouvert est à traiter.' : "$n litiges ouverts sont à traiter.");
    }

    private function avisSignales(Carbon $maintenant): ?array
    {
        $requete = DB::table('avis')->whereNotNull('signale_le')->whereNull('modere_le')->whereNull('masque_le');

        return $this->parAge($requete, 'avis.id', 'avis.signale_le', $maintenant, 'avis_signales',
            fn (int $n) => $n === 1 ? '1 avis signalé est à modérer.' : "$n avis signalés sont à modérer.");
    }

    /** Retraits EN_COURS depuis plus longtemps que le délai de blocage : de l'argent débité dont le versement n'aboutit pas. */
    private function retraitsBloques(Carbon $maintenant): ?array
    {
        $limite = $maintenant->copy()->subMinutes((int) config('sopi.retraits.delai_blocage_minutes'));
        $requete = DB::table('retraits')->where('statut', StatutRetrait::EnCours->value)->where('created_at', '<=', $limite);
        [$compteur, $ids, $plusAncien] = $this->lire($requete, 'retraits.id', 'retraits.created_at');
        if ($compteur === 0) {
            return null;
        }

        $age = $this->minutes($plusAncien, $maintenant);
        $gravite = $age >= (int) config('sopi.admin.alertes.retraits_critique_minutes') ? self::CRITIQUE : self::ATTENTION;

        return $this->alerte('retraits_bloques', $gravite, $compteur, $ids, $age, null,
            $compteur === 1 ? '1 retrait est bloqué en cours de traitement.' : "$compteur retraits sont bloqués en cours de traitement.");
    }

    /** Remboursements dus dont le nombre maximal d'essais est atteint : plus aucune reprise automatique, intervention nécessaire. */
    private function remboursementsEnEchec(Carbon $maintenant): ?array
    {
        $requete = $this->remboursementsDus()->where('remboursement_tentatives', '>=', (int) config('sopi.paiement.remboursement_max_tentatives'));
        [$compteur, $ids, $plusAncien] = $this->lire($requete, 'paiements.id', 'paiements.remboursement_du_le');
        if ($compteur === 0) {
            return null;
        }

        return $this->alerte('remboursements_en_echec', self::CRITIQUE, $compteur, $ids, $this->minutes($plusAncien, $maintenant), null,
            $compteur === 1 ? '1 remboursement a échoué après tous les essais.' : "$compteur remboursements ont échoué après tous les essais.");
    }

    /** Remboursements dus depuis plus de `remboursements_retard_minutes`, encore en cours de reprise automatique. */
    private function remboursementsEnRetard(Carbon $maintenant): ?array
    {
        $limite = $maintenant->copy()->subMinutes((int) config('sopi.admin.alertes.remboursements_retard_minutes'));
        $requete = $this->remboursementsDus()
            ->where('remboursement_tentatives', '<', (int) config('sopi.paiement.remboursement_max_tentatives'))
            ->where('remboursement_du_le', '<=', $limite);
        [$compteur, $ids, $plusAncien] = $this->lire($requete, 'paiements.id', 'paiements.remboursement_du_le');
        if ($compteur === 0) {
            return null;
        }

        return $this->alerte('remboursements_en_retard', self::ATTENTION, $compteur, $ids, $this->minutes($plusAncien, $maintenant), null,
            $compteur === 1 ? '1 remboursement est en retard.' : "$compteur remboursements sont en retard.");
    }

    /** Notifications dont l'envoi a échoué définitivement (après le nombre maximal d'essais) sur au moins un canal. */
    private function notificationsEnEchec(Carbon $maintenant): ?array
    {
        $requete = DB::table('notifications')->where(function ($q) {
            $q->where('canal_email_statut', StatutEnvoi::Echoue->value)->orWhere('canal_push_statut', StatutEnvoi::Echoue->value);
        });
        [$compteur, $ids, $plusAncien] = $this->lire($requete, 'notifications.id', 'notifications.created_at');
        if ($compteur === 0) {
            return null;
        }

        $gravite = $compteur >= (int) config('sopi.admin.alertes.notifications_critique_seuil') ? self::CRITIQUE : self::ATTENTION;

        return $this->alerte('notifications_en_echec', $gravite, $compteur, $ids, $this->minutes($plusAncien, $maintenant), null,
            $compteur === 1 ? '1 notification n\'a pas pu être envoyée.' : "$compteur notifications n'ont pas pu être envoyées.");
    }

    /** Suspensions temporaires non levées qui se terminent dans les prochaines heures (rappel : le compte sera réactivé). */
    private function suspensionsQuiPrennentFin(Carbon $maintenant): ?array
    {
        $horizon = $maintenant->copy()->addHours((int) config('sopi.admin.alertes.suspension_expiration_heures'));
        $requete = DB::table('sanctions')
            ->where('type', TypeSanction::SuspensionTemporaire->value)
            ->whereNull('levee_le')
            ->where('date_fin', '>', $maintenant)
            ->where('date_fin', '<=', $horizon);
        [$compteur, $ids, $premiere] = $this->lire($requete, 'sanctions.id', 'sanctions.date_fin');
        if ($compteur === 0) {
            return null;
        }

        return $this->alerte('suspensions_a_echeance', self::INFO, $compteur, $ids, null, max(0, $this->minutes($maintenant, $premiere)),
            $compteur === 1 ? '1 suspension temporaire se termine bientôt.' : "$compteur suspensions temporaires se terminent bientôt.");
    }

    private function remboursementsDus(): Builder
    {
        return DB::table('paiements')
            ->whereIn('statut', [StatutPaiement::Reussi->value, StatutPaiement::Echoue->value])
            ->whereNotNull('remboursement_du_le');
    }

    /** Alerte dont la gravité dépend de l'ancienneté du plus ancien élément (info, puis attention, puis critique). */
    private function parAge(Builder $requete, string $colonneId, string $colonneDate, Carbon $maintenant, string $type, callable $message): ?array
    {
        [$compteur, $ids, $plusAncien] = $this->lire($requete, $colonneId, $colonneDate);
        if ($compteur === 0) {
            return null;
        }

        $age = $this->minutes($plusAncien, $maintenant);
        $gravite = match (true) {
            $age >= (int) config('sopi.admin.alertes.age_critique_heures') * 60 => self::CRITIQUE,
            $age >= (int) config('sopi.admin.alertes.age_attention_heures') * 60 => self::ATTENTION,
            default => self::INFO,
        };

        return $this->alerte($type, $gravite, $compteur, $ids, $age, null, $message($compteur));
    }

    /**
     * Une requête : les éléments les plus anciens (au plus ids_max) et le total complet via COUNT(*) OVER ().
     *
     * @return array{0: int, 1: list<string>, 2: ?Carbon} total, identifiants, date du plus ancien
     */
    private function lire(Builder $requete, string $colonneId, string $colonneDate): array
    {
        $lignes = $requete
            ->selectRaw("$colonneId AS id, $colonneDate AS date_reference, COUNT(*) OVER () AS total")
            ->orderBy($colonneDate)
            ->orderBy($colonneId)
            ->limit(max(1, (int) config('sopi.admin.alertes.ids_max')))
            ->get();

        if ($lignes->isEmpty()) {
            return [0, [], null];
        }

        return [(int) $lignes->first()->total, $lignes->pluck('id')->all(), Carbon::parse($lignes->first()->date_reference)];
    }

    /** Minutes écoulées entre deux instants (entier, jamais négatif). */
    private function minutes(?Carbon $depuis, Carbon $jusqua): int
    {
        return $depuis === null ? 0 : max(0, (int) floor($depuis->diffInMinutes($jusqua, false)));
    }

    private function alerte(string $type, string $gravite, int $compteur, array $ids, ?int $ageMinutes, ?int $echeanceDansMinutes, string $message): array
    {
        return [
            'type' => $type,
            'ressource' => self::RESSOURCES[$type],
            'gravite' => $gravite,
            'compteur' => $compteur,
            'message' => $message,
            'ids' => $ids,
            'ageMinutes' => $ageMinutes,
            'echeanceDansMinutes' => $echeanceDansMinutes,
        ];
    }
}
