<?php

namespace App\Http\Requests\Client;

use App\Services\AgendaClientService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class ConsulterAgendaPrestataireRequest extends FormRequest
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
            if ($du->lt(now()->startOfDay())) {
                $validateur->errors()->add('du', 'La date de début ne peut pas être dans le passé.');
            } elseif ($au->lt($du)) {
                $validateur->errors()->add('au', 'La date de fin doit être après la date de début.');
            } elseif ($du->copy()->startOfDay()->diffInDays($au->copy()->startOfDay()) > AgendaClientService::JOURS_MAX) {
                $validateur->errors()->add('au', 'L\'intervalle ne peut pas dépasser '.AgendaClientService::JOURS_MAX.' jours.');
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
            'du' => ['description' => 'Premier jour (AAAA-MM-JJ), aujourd\'hui ou plus tard. Par défaut : maintenant.', 'example' => '2030-01-15', 'required' => false],
            'au' => ['description' => 'Dernier jour inclus (AAAA-MM-JJ). Par défaut : '.AgendaClientService::JOURS_PAR_DEFAUT.' jours après le début. Intervalle maximal : '.AgendaClientService::JOURS_MAX.' jours.', 'example' => '2030-01-29', 'required' => false],
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} Début de l'intervalle (maintenant par défaut) et dernier jour inclus. */
    public function intervalle(): array
    {
        $fuseau = config('app.timezone');
        $du = $this->query('du') ? Carbon::createFromFormat('Y-m-d', $this->query('du'), $fuseau)->startOfDay() : now();
        $au = $this->query('au')
            ? Carbon::createFromFormat('Y-m-d', $this->query('au'), $fuseau)->startOfDay()
            : $du->copy()->addDays(AgendaClientService::JOURS_PAR_DEFAUT);

        return [$du, $au];
    }
}
