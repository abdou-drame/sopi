<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RefuserDemandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('motif'))) {
            $this->merge(['motif' => trim($this->input('motif'))]);
        }
    }

    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motif.required' => 'Le motif du refus est obligatoire.',
            'motif.string' => 'Le motif du refus doit être un texte.',
            'motif.min' => 'Le motif du refus doit contenir au moins 10 caractères.',
            'motif.max' => 'Le motif du refus ne doit pas dépasser 500 caractères.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'motif' => ['description' => 'Raison du refus, communiquée au prestataire (10 à 500 caractères).', 'example' => 'La photocopie de la CNI est illisible.'],
        ];
    }
}
