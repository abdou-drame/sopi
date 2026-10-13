<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListeLitigesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['nullable', 'string', Rule::in(['ouvert', 'cloture', 'tous'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['statut.in' => 'Le statut doit être ouvert, cloture ou tous.'];
    }

    public function statut(): string
    {
        return $this->validated('statut') ?? 'ouvert';
    }

    public function queryParameters(): array
    {
        return [
            'statut' => ['description' => 'ouvert (défaut), cloture ou tous.', 'example' => 'ouvert'],
            'page' => ['description' => 'Numéro de page (20 résultats par page).', 'example' => 1],
        ];
    }
}
