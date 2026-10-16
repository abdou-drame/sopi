<?php

namespace App\Console\Commands;

use App\Enums\TypeMouvementPortefeuille;
use App\Models\MouvementPortefeuille;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Retrait;
use App\Services\Paiement\CalculPaiement;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reconstitue le journal des mouvements pour le passé (avant la mise en place du journal), à partir des paiements crédités et
 * des retraits. Idempotente : un mouvement n'est créé que s'il n'existe pas déjà pour (type, réservation) ou (type, retrait).
 * Le « solde après » est recalculé en remontant depuis le solde actuel : il est exact si le solde n'a bougé que par ces
 * opérations. À lancer à la main après le déploiement ; --dry-run n'écrit rien.
 */
class ReconstituerHistoriquePortefeuille extends Command
{
    protected $signature = 'sopi:portefeuille:reconstituer-historique {--dry-run : Affiche ce qui serait créé sans rien écrire}';

    protected $description = 'Reconstitue les mouvements passés du portefeuille (crédits, retraits, recrédits) sans doublon';

    public function handle(): int
    {
        $sec = (bool) $this->option('dry-run');
        $total = 0;

        foreach (Prestataire::query()->orderBy('id')->cursor() as $prestataire) {
            $total += DB::transaction(fn () => $this->traiter($prestataire, $sec));
        }

        $this->info(($sec ? '[simulation] ' : '').$total.' mouvement(s) '.($sec ? 'à créer' : 'créé(s)').'.');

        return self::SUCCESS;
    }

    private function traiter(Prestataire $prestataire, bool $sec): int
    {
        $evenements = $this->evenements($prestataire);
        if ($evenements->isEmpty()) {
            return 0;
        }

        $existants = MouvementPortefeuille::query()->where('prestataire_id', $prestataire->id)->get();
        $dejaVus = $existants->map(fn ($m) => $this->cle($m->type, $m->reservation_id, $m->retrait_id))->all();

        // Tous les événements (déjà journalisés ou non), du plus ancien au plus récent ; le solde après se calcule en remontant.
        $evenements = $evenements->sortBy(fn ($e) => $e['date']->getTimestamp())->values();
        $solde = self::centimes((string) Prestataire::query()->whereKey($prestataire->id)->value('solde'));
        $apres = [];
        for ($i = $evenements->count() - 1; $i >= 0; $i--) {
            $apres[$i] = $solde;
            $solde -= $evenements[$i]['signe'] * self::centimes($evenements[$i]['montant']);
        }

        $crees = 0;
        foreach ($evenements as $i => $e) {
            if (in_array($this->cle($e['type'], $e['reservation_id'], $e['retrait_id']), $dejaVus, true)) {
                continue;
            }
            $crees++;
            if ($sec) {
                continue;
            }
            $mouvement = MouvementPortefeuille::enregistrer(
                $prestataire->id, $e['type'], $e['montant'], $e['libelle'], number_format($apres[$i] / 100, 2, '.', ''),
                $e['reservation_id'], $e['retrait_id'],
            );
            $mouvement->forceFill(['created_at' => $e['date'], 'updated_at' => $e['date']])->saveQuietly();
        }

        return $crees;
    }

    /** @return Collection<int, array{type: TypeMouvementPortefeuille, montant: string, libelle: string, date: CarbonInterface, signe: int, reservation_id: ?string, retrait_id: ?string}> */
    private function evenements(Prestataire $prestataire): Collection
    {
        $evenements = collect();

        Paiement::query()
            ->whereNotNull('credite_le')
            ->whereHas('reservation.creneau', fn ($q) => $q->where('prestataire_id', $prestataire->id))
            ->get()
            ->each(function (Paiement $p) use ($evenements) {
                $evenements->push([
                    'type' => TypeMouvementPortefeuille::Credit,
                    'montant' => CalculPaiement::soustraire($p->montant_acompte, $p->commission),
                    'libelle' => 'Prestation terminée (acompte moins commission)',
                    'date' => $p->credite_le, 'signe' => 1, 'reservation_id' => $p->reservation_id, 'retrait_id' => null,
                ]);
            });

        Retrait::query()->where('prestataire_id', $prestataire->id)->get()->each(function (Retrait $r) use ($evenements) {
            $montant = number_format((float) $r->montant, 2, '.', '');
            $evenements->push([
                'type' => TypeMouvementPortefeuille::Retrait, 'montant' => $montant, 'libelle' => 'Demande de retrait',
                'date' => $r->created_at, 'signe' => -1, 'reservation_id' => null, 'retrait_id' => $r->id,
            ]);
            if ($r->recredite_le !== null) {
                $evenements->push([
                    'type' => TypeMouvementPortefeuille::RemboursementRetrait, 'montant' => $montant, 'libelle' => 'Retrait échoué : montant recrédité',
                    'date' => $r->recredite_le, 'signe' => 1, 'reservation_id' => null, 'retrait_id' => $r->id,
                ]);
            }
        });

        return $evenements;
    }

    /** « 3000.00 » devient 300000 : calcul en centimes entiers, jamais en flottant. */
    private static function centimes(string $montant): int
    {
        return (int) round(((float) $montant) * 100);
    }

    private function cle(TypeMouvementPortefeuille $type, ?string $reservationId, ?string $retraitId): string
    {
        return $type->value.'|'.$reservationId.'|'.$retraitId;
    }
}
