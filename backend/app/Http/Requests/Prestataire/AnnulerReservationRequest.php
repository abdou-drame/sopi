<?php

namespace App\Http\Requests\Prestataire;

use Illuminate\Foundation\Http\FormRequest;

class AnnulerReservationRequest extends FormRequest
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
            'motif.required' => 'Le motif de l\'annulation est obligatoire.',
            'motif.string' => 'Le motif de l\'annulation doit être un texte.',
            'motif.min' => 'Le motif de l\'annulation doit contenir au moins 3 caractères.',
            'motif.max' => 'Le motif de l\'annulation ne doit pas dépasser 500 caractères.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'motif' => ['description' => 'Raison de l\'annulation, transmise au client (3 à 500 caractères).', 'example' => 'Un imprévu m\'empêche d\'assurer la prestation.'],
        ];
    }
}
