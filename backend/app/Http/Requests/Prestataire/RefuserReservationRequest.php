<?php

namespace App\Http\Requests\Prestataire;

use Illuminate\Foundation\Http\FormRequest;

class RefuserReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motif.required' => 'Le motif du refus est obligatoire.',
            'motif.string' => 'Le motif du refus doit être un texte.',
            'motif.min' => 'Le motif du refus doit contenir au moins 3 caractères.',
            'motif.max' => 'Le motif du refus ne doit pas dépasser 500 caractères.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'motif' => ['description' => 'Raison du refus, transmise au client (3 à 500 caractères).', 'example' => 'Je ne suis pas disponible à cette heure.'],
        ];
    }
}
