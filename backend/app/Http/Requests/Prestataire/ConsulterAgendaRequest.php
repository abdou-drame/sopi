<?php

namespace App\Http\Requests\Prestataire;

use App\Services\AgendaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class ConsulterAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validateur) {
            if ($validateur->errors()->isNotEmpty()) {
                return;
            }

            [$du, $au] = $this->intervalle();
            if ($au->lt($du)) {
                $validateur->errors()->add('au', 'La date de fin doit être après la date de début.');
            } elseif ($du->diffInDays($au) > AgendaService::JOURS_MAX_CONSULTATION) {
                $validateur->errors()->add('au', 'L\'intervalle ne peut pas dépasser '.AgendaService::JOURS_MAX_CONSULTATION.' jours.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'du.date_format' => 'La date de début doit être au format AAAA-MM-JJ.',
            'au.date_format' => 'La date de fin doit être au format AAAA-MM-JJ.',
        ];
    }

    public function queryParameters(): array
    {
        return [
            'du' => ['description' => 'Premier jour (AAAA-MM-JJ). Par défaut : aujourd\'hui.', 'example' => '2030-01-15', 'required' => false],
            'au' => ['description' => 'Dernier jour inclus (AAAA-MM-JJ). Par défaut : '.AgendaService::JOURS_PAR_DEFAUT.' jours après `du`. Intervalle maximal : '.AgendaService::JOURS_MAX_CONSULTATION.' jours.', 'example' => '2030-02-15', 'required' => false],
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} Jours de début et de fin (inclus), à minuit dans le fuseau applicatif. */
    public function intervalle(): array
    {
        $fuseau = config('app.timezone');
        $du = $this->query('du') ? Carbon::createFromFormat('Y-m-d', $this->query('du'), $fuseau)->startOfDay() : now()->startOfDay();
        $au = $this->query('au')
            ? Carbon::createFromFormat('Y-m-d', $this->query('au'), $fuseau)->startOfDay()
            : $du->copy()->addDays(AgendaService::JOURS_PAR_DEFAUT);

        return [$du, $au];
    }
}
