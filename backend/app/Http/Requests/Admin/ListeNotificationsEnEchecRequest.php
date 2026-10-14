<?php

namespace App\Http\Requests\Admin;

use App\Enums\Canal;
use App\Enums\TypeNotification;
use App\Http\Requests\Admin\Concerns\FiltresDeListe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtres de GET /admin/notifications-en-echec : canal en échec, type, ancienneté minimale. */
class ListeNotificationsEnEchecRequest extends FormRequest
{
    use FiltresDeListe;

    public function authorize(): bool
    {
        return true;
    }

    protected function alias(): array
    {
        return ['anciennete_minutes' => 'ancienneteMinutes'];
    }

    public function rules(): array
    {
        return [
            'canal' => ['nullable', 'string', Rule::in(array_column(Canal::cases(), 'value'))],
            'type' => ['nullable', 'string', Rule::in(array_column(TypeNotification::cases(), 'value'))],
            'ancienneteMinutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ] + $this->reglesCommunes(false);
    }

    public function messages(): array
    {
        return [
            'canal.in' => 'Le canal doit être email ou push.',
            'type.in' => 'Le type de notification est inconnu.',
            'ancienneteMinutes.integer' => 'L\'ancienneté doit être un nombre entier de minutes.',
            'ancienneteMinutes.min' => 'L\'ancienneté doit être d\'au moins 1 minute.',
            'ancienneteMinutes.max' => 'L\'ancienneté ne peut pas dépasser 525600 minutes (un an).',
        ] + $this->messagesCommuns();
    }

    public function queryParameters(): array
    {
        return [
            'canal' => ['description' => 'email ou push : ne garde que les notifications dont CE canal est en échec.', 'example' => 'email', 'required' => false],
            'type' => ['description' => 'Type de notification, par exemple reservation_confirmee.', 'example' => 'reservation_confirmee', 'required' => false],
            'ancienneteMinutes' => ['description' => 'Notifications créées depuis au moins N minutes (alias : anciennete_minutes).', 'example' => 60, 'required' => false],
            'page' => ['description' => 'Numéro de page (20 résultats par page).', 'example' => 1, 'required' => false],
        ];
    }

    /** @return array{canal: ?string, type: ?string, ancienneteMinutes: ?int} */
    public function filtres(): array
    {
        $v = $this->validated();

        return [
            'canal' => $v['canal'] ?? null,
            'type' => $v['type'] ?? null,
            'ancienneteMinutes' => isset($v['ancienneteMinutes']) ? (int) $v['ancienneteMinutes'] : null,
        ];
    }
}
