<?php

namespace App\Services;

use App\Enums\StatutCompte;
use App\Models\Prestataire;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche de prestataires par le client (RF02, RG04) : comptes actifs ayant au moins une prestation active.
 *
 * Les comparaisons de texte passent par lower() sur les deux côtés. Limite : la recherche n'ignore pas les accents
 * (« Elagage » ne trouve pas « Élagage »), et la mise en minuscules des lettres accentuées dépend du moteur.
 */
class RechercheService
{
    public const PAR_PAGE = 20;

    /** Caractère d'échappement des jokers LIKE (« ! » évite toute ambiguïté avec l'antislash selon les moteurs). */
    private const ECHAPPEMENT = '!';

    /**
     * @param  array{metier?: ?string, quartier?: ?string, q?: ?string, prix_min?: int|float|string|null, prix_max?: int|float|string|null, tri?: ?string}  $filtres
     */
    public function rechercher(array $filtres): LengthAwarePaginator
    {
        $requete = Prestataire::query()
            // prixMin et nombrePrestations portent sur toutes les prestations actives, quels que soient les filtres de prix ou de texte.
            ->withMin(['prestations as prix_min' => fn (Builder $q) => $q->where('actif', true)], 'prix')
            // Note moyenne et nombre d'avis (hors avis masqués) : sous-requêtes agrégées dans la même requête (pas de N+1).
            ->withAvg('avisVisibles as note_moyenne', 'note')
            ->withCount('avisVisibles as nombre_avis')
            ->withCount(['prestations as nombre_prestations' => fn (Builder $q) => $q->where('actif', true)])
            ->whereHas('user', fn (Builder $q) => $q->where('statut', StatutCompte::Actif->value))
            ->whereHas('prestations', fn (Builder $q) => $q->where('actif', true));

        $this->filtrer($requete, $filtres);
        $this->trier($requete, $filtres['tri'] ?? 'nom');

        return $requete->paginate(self::PAR_PAGE);
    }

    private function filtrer(Builder $requete, array $filtres): void
    {
        if (filled($filtres['metier'] ?? null)) {
            $requete->where('prestataires.metier', $filtres['metier']);
        }

        if (filled($filtres['quartier'] ?? null)) {
            $requete->whereRaw('lower(trim(prestataires.quartier)) = ?', [$this->minuscules($filtres['quartier'])]);
        }

        if (filled($filtres['q'] ?? null)) {
            $motif = '%'.$this->echapperLike($this->minuscules($filtres['q'])).'%';
            $like = 'lower(%s) like ? escape \''.self::ECHAPPEMENT.'\'';

            $requete->where(function (Builder $q) use ($motif, $like) {
                $q->whereRaw(sprintf($like, 'prestataires.nom_entreprise'), [$motif])
                    ->orWhereHas('prestations', fn (Builder $p) => $p->where('actif', true)
                        ->whereRaw(sprintf($like, 'prestations.nom'), [$motif]));
            });
        }

        $prixMin = $filtres['prix_min'] ?? null;
        $prixMax = $filtres['prix_max'] ?? null;
        if (filled($prixMin) || filled($prixMax)) {
            // Une même prestation active doit respecter toute la fourchette.
            $requete->whereHas('prestations', fn (Builder $q) => $q->where('actif', true)
                ->when(filled($prixMin), fn (Builder $p) => $p->where('prix', '>=', $prixMin))
                ->when(filled($prixMax), fn (Builder $p) => $p->where('prix', '<=', $prixMax)));
        }
    }

    /** Tri stable (identifiant en dernier critère) pour que la pagination ne répète ni ne saute aucun prestataire. */
    private function trier(Builder $requete, string $tri): void
    {
        match ($tri) {
            'prix_asc' => $requete->orderBy('prix_min'),
            'prix_desc' => $requete->orderByDesc('prix_min'),
            default => null,
        };

        $requete->orderByRaw('lower(prestataires.nom_entreprise)')->orderBy('prestataires.id');
    }

    private function minuscules(string $texte): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $texte)));
    }

    private function echapperLike(string $texte): string
    {
        return str_replace(
            [self::ECHAPPEMENT, '%', '_'],
            [self::ECHAPPEMENT.self::ECHAPPEMENT, self::ECHAPPEMENT.'%', self::ECHAPPEMENT.'_'],
            $texte,
        );
    }
}
