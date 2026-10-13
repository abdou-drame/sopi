<?php

namespace App\Http\Requests\Prestataire;

use Illuminate\Foundation\Http\FormRequest;

class SignalerAvisRequest extends FormRequest
{
    public const MOTIF_MIN = 10;

    public const MOTIF_MAX = 500;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['motif' => ['required', 'string', 'min:'.self::MOTIF_MIN, 'max:'.self::MOTIF_MAX]];
    }

    public function messages(): array
    {
        return [
            'motif.required' => 'Le motif du signalement est obligatoire.',
            'motif.string' => 'Le motif doit être un texte.',
            'motif.min' => 'Le motif doit contenir au moins '.self::MOTIF_MIN.' caractères.',
            'motif.max' => 'Le motif ne peut pas dépasser '.self::MOTIF_MAX.' caractères.',
        ];
    }

    public function bodyParameters(): array
    {
        return ['motif' => ['description' => 'Pourquoi cet avis doit être examiné ('.self::MOTIF_MIN.' à '.self::MOTIF_MAX.' caractères).', 'example' => 'Cet avis concerne une autre prestation que la mienne.']];
    }

    public function motif(): string
    {
        return trim($this->validated('motif'));
    }
}
