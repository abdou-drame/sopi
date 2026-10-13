<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Paramètres de GET /admin/revenus. Les dates sont des jours calendaires (AAAA-MM-JJ) du fuseau de l'application,
 * bornes incluses. Par défaut : les 30 derniers jours (aujourd'hui compris), regroupés par jour.
 */
class RevenusRequest extends FormRequest
{
    public const DUREE_MAX_JOURS = 366;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'debut' => ['nullable', 'date_format:Y-m-d'],
            'fin' => ['nullable', 'date_format:Y-m-d'],
            'regroupement' => ['nullable', 'in:jour,semaine,mois'],
        ];
    }

    public function messages(): array
    {
        return [
            'debut.date_format' => 'La date de début doit être au format AAAA-MM-JJ.',
            'fin.date_format' => 'La date de fin doit être au format AAAA-MM-JJ.',
            'regroupement.in' => 'Le regroupement doit être « jour », « semaine » ou « mois ».',
        ];
    }

    public function withValidator(Validator $validateur): void
    {
        $validateur->after(function (Validator $validateur) {
            if ($validateur->errors()->isNotEmpty()) {
                return;
            }

            $debut = $this->debut();
            $fin = $this->fin();
            if ($fin->lt($debut)) {
                $validateur->errors()->add('fin', 'La date de fin doit être postérieure ou égale à la date de début.');
            } elseif ($debut->diffInDays($fin) + 1 > self::DUREE_MAX_JOURS) {
                $validateur->errors()->add('fin', 'La période ne peut pas dépasser '.self::DUREE_MAX_JOURS.' jours.');
            }
        });
    }

    /** Premier jour (00:00) dans le fuseau de l'application. */
    public function debut(): Carbon
    {
        if ($this->filled('debut')) {
            return Carbon::createFromFormat('Y-m-d', $this->input('debut'))->startOfDay();
        }

        return $this->fin()->copy()->subDays(29)->startOfDay();
    }

    /** Dernier jour (00:00 de ce jour, borne incluse). */
    public function fin(): Carbon
    {
        return $this->filled('fin') ? Carbon::createFromFormat('Y-m-d', $this->input('fin'))->startOfDay() : now()->startOfDay();
    }

    public function regroupement(): string
    {
        return $this->input('regroupement', 'jour');
    }

    public function queryParameters(): array
    {
        return [
            'debut' => ['description' => 'Premier jour de la période (AAAA-MM-JJ, inclus). Par défaut : 29 jours avant la fin.', 'example' => '2030-01-01', 'required' => false],
            'fin' => ['description' => 'Dernier jour de la période (AAAA-MM-JJ, inclus). Par défaut : aujourd\'hui. Au plus 366 jours après le début.', 'example' => '2030-01-31', 'required' => false],
            'regroupement' => ['description' => 'Pas de la série : `jour` (défaut), `semaine` (lundi à dimanche) ou `mois` (mois calendaire).', 'example' => 'semaine', 'required' => false],
        ];
    }
}
