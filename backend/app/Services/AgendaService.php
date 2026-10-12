<?php

namespace App\Services;

use App\Enums\StatutCreneau;
use App\Models\Creneau;
use App\Models\Prestataire;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Agenda d'un prestataire (RF09) : génération de créneaux libres à partir de plages, consultation, suppression.
 * Les dates et heures sont celles du fuseau applicatif (Africa/Dakar), stockées comme le fait la table creneaux.
 *
 * Bornes par défaut (à ajuster si besoin) : voir les constantes.
 */
class AgendaService
{
    public const DUREE_MIN_MINUTES = 15;

    public const DUREE_MAX_MINUTES = 480;

    public const SEMAINES_MAX = 8;

    public const PLAGES_MAX = 7;

    public const CRENEAUX_MAX = 500;

    /** Au-delà, la réponse de création ne contient que le nombre de créneaux, sans la liste. */
    public const LISTE_MAX = 200;

    public const JOURS_PAR_DEFAUT = 30;

    public const JOURS_MAX_CONSULTATION = 62;

    /** Nombre maximal de conflits détaillés dans le message d'erreur. */
    private const CONFLITS_AFFICHES = 20;

    /**
     * Crée tous les créneaux des plages, ou aucun. Les plages sont supposées valides (voir ConfigurerAgendaRequest).
     *
     * @param  list<array{date: string, heure_debut: string, heure_fin: string, duree_creneau_minutes: int|string, repeter_semaines?: int|string|null}>  $plages
     * @return Collection<int, Creneau> Créneaux créés, triés par début.
     *
     * @throws ValidationException si un créneau en chevauche un autre (existant ou de la requête)
     */
    public function configurer(Prestataire $prestataire, array $plages): Collection
    {
        $nouveaux = $this->generer($plages);
        $this->verifierChevauchementsInternes($nouveaux);

        try {
            $ids = DB::transaction(function () use ($prestataire, $nouveaux) {
                // Verrou sur la ligne du prestataire : deux requêtes simultanées se suivent au lieu de se croiser.
                Prestataire::query()->lockForUpdate()->findOrFail($prestataire->id);

                $this->verifierChevauchementsExistants($prestataire, $nouveaux);

                $maintenant = now();
                $lignes = array_map(fn (array $c) => [
                    'id' => (string) Str::uuid7(),
                    'prestataire_id' => $prestataire->id,
                    'date_debut' => $c['debut'],
                    'date_fin' => $c['fin'],
                    'statut' => StatutCreneau::Libre->value,
                    'verrou_expire_a' => null,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ], $nouveaux);

                foreach (array_chunk($lignes, 100) as $lot) {
                    Creneau::query()->insert($lot);
                }

                return array_column($lignes, 'id');
            });
        } catch (UniqueConstraintViolationException) {
            // Garde-fou final (prestataire, début) : un créneau a été créé entre-temps.
            throw ValidationException::withMessages(['plages' => ['Un créneau existe déjà à l\'une de ces heures de début.']]);
        }

        return Creneau::query()->whereIn('id', $ids)->orderBy('date_debut')->get();
    }

    /** Créneaux dont le début tombe entre le début du jour $du et la fin du jour $au (inclus), triés par début. */
    public function consulter(Prestataire $prestataire, Carbon $du, Carbon $au): Collection
    {
        return $prestataire->creneaux()
            ->where('date_debut', '>=', $du->copy()->startOfDay())
            ->where('date_debut', '<', $au->copy()->addDay()->startOfDay())
            ->orderBy('date_debut')
            ->get();
    }

    /**
     * Supprime un créneau LIBRE du prestataire, de façon atomique (suppression conditionnelle).
     *
     * @return bool|null true si supprimé, false si le créneau n'est plus libre, null s'il n'existe pas ou n'est pas le sien
     */
    public function supprimer(Prestataire $prestataire, string $id): ?bool
    {
        $supprimes = $prestataire->creneaux()
            ->whereKey($id)
            ->where('statut', StatutCreneau::Libre->value)
            ->delete();

        if ($supprimes > 0) {
            return true;
        }

        return $prestataire->creneaux()->whereKey($id)->exists() ? false : null;
    }

    /**
     * @param  list<array<string, mixed>>  $plages
     * @return list<array{debut: Carbon, fin: Carbon}> Triés par début.
     */
    private function generer(array $plages): array
    {
        $fuseau = config('app.timezone');
        $creneaux = [];

        foreach ($plages as $plage) {
            $duree = (int) $plage['duree_creneau_minutes'];
            $repetitions = (int) ($plage['repeter_semaines'] ?? 0);

            for ($semaine = 0; $semaine <= $repetitions; $semaine++) {
                $debutPlage = Carbon::createFromFormat('Y-m-d H:i', $plage['date'].' '.$plage['heure_debut'], $fuseau)->addWeeks($semaine);
                $finPlage = Carbon::createFromFormat('Y-m-d H:i', $plage['date'].' '.$plage['heure_fin'], $fuseau)->addWeeks($semaine);

                for ($debut = $debutPlage->copy(); $debut->copy()->addMinutes($duree)->lte($finPlage); $debut->addMinutes($duree)) {
                    $creneaux[] = ['debut' => $debut->copy(), 'fin' => $debut->copy()->addMinutes($duree)];
                }
            }
        }

        usort($creneaux, fn (array $a, array $b) => $a['debut']->getTimestamp() <=> $b['debut']->getTimestamp());

        return $creneaux;
    }

    /** @param  list<array{debut: Carbon, fin: Carbon}>  $creneaux  Triés par début. */
    private function verifierChevauchementsInternes(array $creneaux): void
    {
        $conflits = [];
        $finMax = null;

        foreach ($creneaux as $creneau) {
            if ($finMax !== null && $creneau['debut']->lt($finMax)) {
                $conflits[] = $this->decrire($creneau).' chevauche un autre créneau de la requête.';
            }
            $finMax = $finMax === null || $creneau['fin']->gt($finMax) ? $creneau['fin'] : $finMax;
        }

        $this->refuserSiConflits($conflits);
    }

    /** @param  list<array{debut: Carbon, fin: Carbon}>  $creneaux */
    private function verifierChevauchementsExistants(Prestataire $prestataire, array $creneaux): void
    {
        if ($creneaux === []) {
            return;
        }

        $debutMin = $creneaux[0]['debut'];
        $finMax = collect($creneaux)->max(fn (array $c) => $c['fin']->getTimestamp());

        // Un seul accès à la base : les créneaux existants qui touchent la fenêtre couverte par la requête (tous statuts).
        $existants = $prestataire->creneaux()
            ->where('date_debut', '<', Carbon::createFromTimestamp($finMax, config('app.timezone')))
            ->where('date_fin', '>', $debutMin)
            ->get(['date_debut', 'date_fin']);

        $conflits = [];
        foreach ($creneaux as $creneau) {
            $autre = $existants->first(fn (Creneau $e) => $e->date_debut->lt($creneau['fin']) && $e->date_fin->gt($creneau['debut']));
            if ($autre !== null) {
                $conflits[] = $this->decrire($creneau).' chevauche un créneau existant ('.$autre->date_debut->format('H:i').' à '.$autre->date_fin->format('H:i').').';
            }
        }

        $this->refuserSiConflits($conflits);
    }

    /** @param  list<string>  $conflits */
    private function refuserSiConflits(array $conflits): void
    {
        if ($conflits === []) {
            return;
        }

        $messages = array_slice($conflits, 0, self::CONFLITS_AFFICHES);
        if (count($conflits) > self::CONFLITS_AFFICHES) {
            $messages[] = '… et '.(count($conflits) - self::CONFLITS_AFFICHES).' autre(s) conflit(s). Aucun créneau n\'a été créé.';
        }

        throw ValidationException::withMessages(['plages' => $messages]);
    }

    /** @param  array{debut: Carbon, fin: Carbon}  $creneau */
    private function decrire(array $creneau): string
    {
        return 'Le créneau du '.$creneau['debut']->format('d/m/Y').' de '.$creneau['debut']->format('H:i').' à '.$creneau['fin']->format('H:i');
    }
}
