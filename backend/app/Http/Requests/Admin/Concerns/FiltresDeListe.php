<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Carbon;

/**
 * Socle commun des filtres des listes d'administration (M12) :
 * - les filtres sont documentés en camelCase (comme `aTraiter` de la liste des avis) ; leur équivalent snake_case
 *   (`remboursement_du`) est aussi accepté, via {@see alias()} ;
 * - les booléens acceptent `true`/`false` en plus de `1`/`0` (la règle `boolean` de Laravel refuse les mots) ;
 * - `debut` / `fin` : jours calendaires AAAA-MM-JJ du fuseau de l'application, bornes incluses.
 * La requête qui l'utilise déclare ses booléens par {@see booleens()}.
 */
trait FiltresDeListe
{
    /** @return array<string, string> alias snake_case => nom camelCase du filtre */
    protected function alias(): array
    {
        return [];
    }

    /** @return list<string> filtres booléens */
    protected function booleens(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        foreach ($this->alias() as $snake => $camel) {
            if ($this->has($snake) && ! $this->has($camel)) {
                $this->merge([$camel => $this->input($snake)]);
            }
        }

        foreach ($this->booleens() as $cle) {
            $valeur = $this->input($cle);
            if (is_string($valeur) && in_array(strtolower($valeur), ['true', 'false'], true)) {
                $this->merge([$cle => strtolower($valeur) === 'true']);
            }
        }
    }

    /** Règles de pagination et de période, à fusionner dans rules(). */
    protected function reglesCommunes(bool $periode = true): array
    {
        return ['page' => ['nullable', 'integer', 'min:1']]
            + ($periode ? ['debut' => ['nullable', 'date_format:Y-m-d'], 'fin' => ['nullable', 'date_format:Y-m-d']] : []);
    }

    protected function messagesCommuns(): array
    {
        return [
            'page.integer' => 'La page doit être un entier.',
            'page.min' => 'La page doit être au moins 1.',
            'debut.date_format' => 'La date de début doit être au format AAAA-MM-JJ.',
            'fin.date_format' => 'La date de fin doit être au format AAAA-MM-JJ.',
        ];
    }

    public function withValidator(Validator $validateur): void
    {
        $validateur->after(function (Validator $validateur) {
            if ($validateur->errors()->isEmpty() && $this->debut() !== null && $this->fin() !== null && $this->fin()->lt($this->debut())) {
                $validateur->errors()->add('fin', 'La date de fin doit être postérieure ou égale à la date de début.');
            }
        });
    }

    /** Premier instant du jour de début (inclus), ou null. */
    public function debut(): ?Carbon
    {
        $valeur = $this->input('debut');

        return filled($valeur) ? Carbon::createFromFormat('Y-m-d', $valeur, config('app.timezone'))->startOfDay() : null;
    }

    /** Dernier jour demandé (à minuit) : la borne haute est le lendemain, exclu, voir {@see finExclue()}. */
    public function fin(): ?Carbon
    {
        $valeur = $this->input('fin');

        return filled($valeur) ? Carbon::createFromFormat('Y-m-d', $valeur, config('app.timezone'))->startOfDay() : null;
    }

    /** Borne haute exclue : minuit du lendemain du jour de fin. */
    public function finExclue(): ?Carbon
    {
        return $this->fin()?->copy()->addDay();
    }

    /** Valeur d'un filtre booléen validé, ou null s'il est absent. */
    public function booleen(string $cle): ?bool
    {
        return $this->filled($cle) ? $this->boolean($cle) : null;
    }
}
