<?php

namespace App\Services;

use App\Enums\Metier;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Enums\StatutLitige;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Enums\TypeSanction;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord de l'administrateur (M11.1) : Administrateur.consulterTableauDeBord() du diagramme de classes.
 *
 * Lecture seule. Chaque rubrique est calculée par UNE requête agrégée (COUNT / SUM / GROUP BY), quel que soit le volume : aucune
 * boucle de requêtes. Aucune donnée personnelle n'est renvoyée, seulement des compteurs et des montants globaux.
 * Les fenêtres « 7 derniers jours » et « 30 derniers jours » sont glissantes, comptées à rebours depuis l'instant de la demande.
 * Les dates stockées sont comparées dans le fuseau de l'application (config app.timezone, Africa/Dakar, identique à UTC).
 */
class StatistiquesAdminService
{
    public function tableauDeBord(): array
    {
        $maintenant = now();

        return [
            'dateGeneration' => $maintenant->toIso8601String(),
            'comptes' => $this->comptes(),
            'reservations' => $this->reservations($maintenant),
            'paiements' => $this->paiements(),
            'litiges' => $this->litiges(),
            'avis' => $this->avis(),
            'sanctions' => $this->sanctions($maintenant),
        ];
    }

    /** Effectifs par rôle ; clients et prestataires détaillés par statut de compte. Les dossiers en attente sont les prestataires EN_ATTENTE_VALIDATION. */
    private function comptes(): array
    {
        $lignes = DB::table('users')->selectRaw('role, statut, COUNT(*) AS nombre')->groupBy('role', 'statut')->get();

        $parRole = [Role::Client->value => $this->zeros(StatutCompte::class), Role::Prestataire->value => $this->zeros(StatutCompte::class)];
        $administrateurs = 0;
        foreach ($lignes as $ligne) {
            if ($ligne->role === Role::Administrateur->value) {
                $administrateurs += (int) $ligne->nombre;
            } elseif (isset($parRole[$ligne->role][$ligne->statut])) {
                $parRole[$ligne->role][$ligne->statut] = (int) $ligne->nombre;
            }
        }

        return [
            'clients' => ['total' => array_sum($parRole[Role::Client->value]), 'parStatut' => $parRole[Role::Client->value]],
            'prestataires' => [
                'total' => array_sum($parRole[Role::Prestataire->value]),
                'parStatut' => $parRole[Role::Prestataire->value],
                'dossiersEnAttente' => $parRole[Role::Prestataire->value][StatutCompte::EnAttenteValidation->value],
            ],
            'administrateurs' => $administrateurs,
        ];
    }

    /**
     * Total et répartition par statut, par domaine de métier, et activité récente. Les réservations « terminées » d'une fenêtre sont
     * celles dont le statut est TERMINEE et dont la dernière modification (le passage à TERMINEE, rien ne les modifie ensuite) est dans la fenêtre.
     */
    private function reservations($maintenant): array
    {
        $il7 = $maintenant->copy()->subDays(7);
        $il30 = $maintenant->copy()->subDays(30);
        $terminee = StatutReservation::Terminee->value;

        $lignes = DB::table('reservations')
            ->selectRaw(
                'statut, COUNT(*) AS nombre,'
                .' SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS creees7,'
                .' SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS creees30,'
                ." SUM(CASE WHEN statut = '$terminee' AND updated_at >= ? THEN 1 ELSE 0 END) AS terminees7,"
                ." SUM(CASE WHEN statut = '$terminee' AND updated_at >= ? THEN 1 ELSE 0 END) AS terminees30",
                [$il7, $il30, $il7, $il30]
            )
            ->groupBy('statut')
            ->get();

        $parStatut = $this->zeros(StatutReservation::class);
        $recent = ['creees7' => 0, 'creees30' => 0, 'terminees7' => 0, 'terminees30' => 0];
        foreach ($lignes as $ligne) {
            if (isset($parStatut[$ligne->statut])) {
                $parStatut[$ligne->statut] = (int) $ligne->nombre;
            }
            foreach ($recent as $cle => $_) {
                $recent[$cle] += (int) $ligne->$cle;
            }
        }

        // Par domaine : le domaine de la prestation réservée (une jointure, une requête).
        $parDomaine = [];
        foreach (Metier::cases() as $metier) {
            $parDomaine[$metier->value] = ['total' => 0, 'parStatut' => $this->zeros(StatutReservation::class)];
        }
        $lignesDomaine = DB::table('reservations')
            ->join('prestations', 'prestations.id', '=', 'reservations.prestation_id')
            ->selectRaw('prestations.domaine AS domaine, reservations.statut AS statut, COUNT(*) AS nombre')
            ->groupBy('prestations.domaine', 'reservations.statut')
            ->get();
        foreach ($lignesDomaine as $ligne) {
            if (isset($parDomaine[$ligne->domaine]['parStatut'][$ligne->statut])) {
                $parDomaine[$ligne->domaine]['parStatut'][$ligne->statut] = (int) $ligne->nombre;
                $parDomaine[$ligne->domaine]['total'] += (int) $ligne->nombre;
            }
        }

        return [
            'total' => array_sum($parStatut),
            'parStatut' => $parStatut,
            'parDomaine' => $parDomaine,
            'activiteRecente' => [
                'derniers7Jours' => ['creees' => $recent['creees7'], 'terminees' => $recent['terminees7']],
                'derniers30Jours' => ['creees' => $recent['creees30'], 'terminees' => $recent['terminees30']],
            ],
        ];
    }

    /** Nombre et montant (FCFA entiers) des acomptes par statut de paiement. */
    private function paiements(): array
    {
        $lignes = DB::table('paiements')->selectRaw('statut, COUNT(*) AS nombre, SUM(montant_acompte) AS montant')->groupBy('statut')->get();

        $parStatut = [];
        foreach (StatutPaiement::cases() as $statut) {
            $parStatut[$statut->value] = ['nombre' => 0, 'montant' => 0];
        }
        foreach ($lignes as $ligne) {
            if (isset($parStatut[$ligne->statut])) {
                $parStatut[$ligne->statut] = ['nombre' => (int) $ligne->nombre, 'montant' => (int) round((float) $ligne->montant)];
            }
        }

        return [
            'total' => ['nombre' => array_sum(array_column($parStatut, 'nombre')), 'montant' => array_sum(array_column($parStatut, 'montant'))],
            'parStatut' => $parStatut,
        ];
    }

    private function litiges(): array
    {
        $parStatut = $this->zeros(StatutLitige::class);
        foreach (DB::table('litiges')->selectRaw('statut, COUNT(*) AS nombre')->groupBy('statut')->get() as $ligne) {
            if (isset($parStatut[$ligne->statut])) {
                $parStatut[$ligne->statut] = (int) $ligne->nombre;
            }
        }

        return ['total' => array_sum($parStatut), 'ouverts' => $parStatut[StatutLitige::Ouvert->value], 'clos' => $parStatut[StatutLitige::Cloture->value]];
    }

    /**
     * Avis : total, signalés à modérer (signalés, ni modérés ni masqués), masqués, et note moyenne globale des avis visibles
     * (non masqués), arrondie à 2 décimales ; null s'il n'y en a aucun.
     */
    private function avis(): array
    {
        $ligne = DB::table('avis')->selectRaw(
            'COUNT(*) AS total,'
            .' SUM(CASE WHEN signale_le IS NOT NULL AND modere_le IS NULL AND masque_le IS NULL THEN 1 ELSE 0 END) AS signales,'
            .' SUM(CASE WHEN masque_le IS NOT NULL THEN 1 ELSE 0 END) AS masques,'
            .' AVG(CASE WHEN masque_le IS NULL THEN note END) AS moyenne'
        )->first();

        return [
            'total' => (int) $ligne->total,
            'signales' => (int) $ligne->signales,
            'masques' => (int) $ligne->masques,
            'noteMoyenne' => $ligne->moyenne === null ? null : round((float) $ligne->moyenne, 2),
        ];
    }

    /** Sanctions actives (non levées) : suspensions dont la date de fin n'est pas passée, et bannissements définitifs. */
    private function sanctions($maintenant): array
    {
        $suspension = TypeSanction::SuspensionTemporaire->value;
        $bannissement = TypeSanction::BannissementDefinitif->value;

        $ligne = DB::table('sanctions')
            ->whereNull('levee_le')
            ->selectRaw(
                "SUM(CASE WHEN type = '$suspension' AND (date_fin IS NULL OR date_fin > ?) THEN 1 ELSE 0 END) AS suspensions,"
                ." SUM(CASE WHEN type = '$bannissement' THEN 1 ELSE 0 END) AS bannissements",
                [$maintenant]
            )
            ->first();

        return ['suspensionsEnCours' => (int) $ligne->suspensions, 'bannissements' => (int) $ligne->bannissements];
    }

    /** @param  class-string<\BackedEnum>  $enum */
    private function zeros(string $enum): array
    {
        return array_fill_keys(array_map(fn ($cas) => $cas->value, $enum::cases()), 0);
    }
}
