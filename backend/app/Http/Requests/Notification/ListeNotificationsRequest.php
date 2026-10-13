<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class ListeNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Dans une URL, le booléen arrive en texte (« true » / « false ») : on l'accepte en plus de 1 / 0. */
    protected function prepareForValidation(): void
    {
        $valeur = $this->query('nonLues');
        if (is_string($valeur) && in_array(strtolower($valeur), ['true', 'false'], true)) {
            $this->merge(['nonLues' => strtolower($valeur) === 'true']);
        }
    }

    public function rules(): array
    {
        return [
            'nonLues' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'nonLues.boolean' => 'Le filtre « non lues » doit être vrai ou faux.',
            'page.integer' => 'La page doit être un nombre entier.',
            'page.min' => 'La page doit être au moins 1.',
        ];
    }

    public function queryParameters(): array
    {
        return [
            'nonLues' => ['description' => 'Si vrai, ne renvoie que les notifications non lues.', 'example' => true, 'required' => false],
            'page' => ['description' => 'Numéro de page (20 notifications par page).', 'example' => 1, 'required' => false],
        ];
    }
}
