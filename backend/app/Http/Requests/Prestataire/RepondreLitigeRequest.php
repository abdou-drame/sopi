<?php

namespace App\Http\Requests\Prestataire;

use Illuminate\Foundation\Http\FormRequest;

class RepondreLitigeRequest extends FormRequest
{
    public const REPONSE_MIN = 10;

    public const REPONSE_MAX = 1000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reponse' => ['required', 'string', 'min:'.self::REPONSE_MIN, 'max:'.self::REPONSE_MAX],
        ];
    }

    public function messages(): array
    {
        return [
            'reponse.required' => 'Votre réponse est obligatoire.',
            'reponse.string' => 'La réponse doit être un texte.',
            'reponse.min' => 'La réponse doit contenir au moins '.self::REPONSE_MIN.' caractères.',
            'reponse.max' => 'La réponse ne peut pas dépasser '.self::REPONSE_MAX.' caractères.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'reponse' => ['description' => 'Votre version des faits, de '.self::REPONSE_MIN.' à '.self::REPONSE_MAX.' caractères. Une seule réponse par litige.', 'example' => 'J\'étais sur place à l\'heure convenue.'],
        ];
    }

    public function reponse(): string
    {
        return trim($this->validated('reponse'));
    }
}
