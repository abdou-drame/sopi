<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListeDemandesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['nullable', 'string', Rule::in(['en_attente', 'actif', 'refuse', 'tous'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['statut.in' => 'Le statut doit être en_attente, actif, refuse ou tous.'];
    }

    public function statut(): string
    {
        return $this->validated('statut') ?? 'en_attente';
    }

    public function queryParameters(): array
    {
        return [
            'statut' => ['description' => 'en_attente (défaut), actif, refuse ou tous.', 'example' => 'en_attente'],
            'page' => ['description' => 'Numéro de page (20 résultats par page).', 'example' => 1],
        ];
    }
}
