<?php

namespace App\Services;

use App\Enums\StatutPaiement;
use App\Enums\StatutRetrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Revenus de la plateforme (M11.2) : Administrateur.consulterRevenus(periode) du diagramme de classes.
 *
 * Définition retenue (le cahier ne définit pas « revenu ») :
 *  - revenus = somme des commissions des paiements REUSSI dont le crédit au prestataire a été fait (credite_le renseigné,
 *    donc prestation terminée ou annulation par le client d'une réservation payée), datés par credite_le. Un paiement remboursé n'est jamais compté.
 *  - acomptesEncaisses = acomptes des paiements REUSSI, datés par paye_le.
 *  - remboursements = acomptes des paiements REMBOURSE, datés par rembourse_le.
 *  - retraitsVerses = montants des retraits REUSSI, datés par traite_le.
 *  - soldePrestataires = somme des soldes des prestataires à l'instant de la demande (cumul, indépendant de la période).
 *
 * Une requête par grandeur, groupée par JOUR calendaire (au plus 366 lignes), puis regroupée en semaines (lundi à dimanche) ou
 * en mois calendaires côté PHP : cela évite les fonctions de date propres à un moteur (date() existe sous PostgreSQL et SQLite).
 * Les jours sont ceux du fuseau de l'application, dans lequel Eloquent écrit les dates. Bornes incluses : de 00:00:00 du premier
 * jour à 23:59:59 du dernier. Montants en FCFA entiers ; les périodes vides figurent dans la série avec des zéros.
 */
class RevenusAdminService
{
    public function revenus(Carbon $debut, Carbon $fin, string $regroupement): array
    {
        $borneBasse = $debut->copy()->startOfDay();
        $borneHaute = $fin->copy()->startOfDay()->addDay(); // exclue : minuit du lendemain du dernier jour

        $grandeurs = [
            'revenus' => $this->parJour('paiements', 'credite_le', 'commission', StatutPaiement::Reussi->value, $borneBasse, $borneHaute, true),
            'acomptesEncaisses' => $this->parJour('paiements', 'paye_le', 'montant_acompte', StatutPaiement::Reussi->value, $borneBasse, $borneHaute),
            'remboursements' => $this->parJour('paiements', 'rembourse_le', 'montant_acompte', StatutPaiement::Rembourse->value, $borneBasse, $borneHaute),
            'retraitsVerses' => $this->parJour('retraits', 'traite_le', 'montant', StatutRetrait::Reussi->value, $borneBasse, $borneHaute),
        ];

        $series = [];
        $totaux = array_fill_keys(array_keys($grandeurs), 0);
        foreach ($this->periodes($debut, $fin, $regroupement) as [$du, $au]) {
            $ligne = ['debut' => $du->toDateString(), 'fin' => $au->toDateString()];
            foreach ($grandeurs as $nom => $parJour) {
                $somme = 0;
                for ($jour = $du->copy(); $jour->lte($au); $jour->addDay()) {
                    $somme += $parJour[$jour->toDateString()] ?? 0;
                }
                $ligne[$nom] = $somme;
                $totaux[$nom] += $somme;
            }
            $series[] = $ligne;
        }

        return [
            'dateGeneration' => now()->toIso8601String(),
            'periode' => [
                'debut' => $debut->toDateString(),
                'fin' => $fin->toDateString(),
                'regroupement' => $regroupement,
                'fuseau' => config('app.timezone'),
            ],
            'totaux' => $totaux,
            'soldePrestataires' => $this->montant(DB::table('prestataires')->sum('solde')),
            'series' => $series,
            'parMetier' => $this->parMetier($borneBasse, $borneHaute),
        ];
    }

    /** Commissions de la période par métier du prestataire (mêmes règles que « revenus »), du plus élevé au plus bas. */
    private function parMetier(Carbon $borneBasse, Carbon $borneHaute): array
    {
        return DB::table('paiements')
            ->join('reservations', 'reservations.id', '=', 'paiements.reservation_id')
            ->join('creneaux', 'creneaux.id', '=', 'reservations.creneau_id')
            ->join('prestataires', 'prestataires.id', '=', 'creneaux.prestataire_id')
            ->where('paiements.statut', StatutPaiement::Reussi->value)
            ->whereNotNull('paiements.credite_le')
            ->where('paiements.credite_le', '>=', $borneBasse)
            ->where('paiements.credite_le', '<', $borneHaute)
            ->selectRaw('prestataires.metier AS metier, SUM(paiements.commission) AS montant')
            ->groupBy('prestataires.metier')
            ->get()
            ->map(fn ($l) => ['metier' => (string) $l->metier, 'revenus' => $this->montant($l->montant)])
            ->sortByDesc('revenus')
            ->values()
            ->all();
    }

    /** @return array<string, int> FCFA entiers par jour (AAAA-MM-JJ) */
    private function parJour(string $table, string $colonneDate, string $colonneMontant, string $statut, Carbon $borneBasse, Carbon $borneHaute, bool $credites = false): array
    {
        $requete = DB::table($table)
            ->where('statut', $statut)
            ->where($colonneDate, '>=', $borneBasse)
            ->where($colonneDate, '<', $borneHaute);
        if ($credites) {
            $requete->whereNotNull('credite_le');
        }

        $jours = [];
        foreach ($requete->selectRaw("date($colonneDate) AS jour, SUM($colonneMontant) AS montant")->groupBy('jour')->get() as $ligne) {
            $jours[(string) $ligne->jour] = $this->montant($ligne->montant);
        }

        return $jours;
    }

    /** @return list<array{0: Carbon, 1: Carbon}> périodes consécutives couvrant [debut, fin], tronquées à ces bornes */
    private function periodes(Carbon $debut, Carbon $fin, string $regroupement): array
    {
        $periodes = [];
        $curseur = $debut->copy()->startOfDay();
        $dernier = $fin->copy()->startOfDay();

        while ($curseur->lte($dernier)) {
            $finNaturelle = match ($regroupement) {
                'semaine' => $curseur->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay(),
                'mois' => $curseur->copy()->endOfMonth()->startOfDay(),
                default => $curseur->copy(),
            };
            $au = $finNaturelle->lt($dernier) ? $finNaturelle : $dernier->copy();
            $periodes[] = [$curseur->copy(), $au];
            $curseur = $au->copy()->addDay();
        }

        return $periodes;
    }

    /** FCFA entiers (PostgreSQL renvoie une chaîne décimale, SQLite un nombre). */
    private function montant(mixed $valeur): int
    {
        return (int) round((float) $valeur);
    }
}
