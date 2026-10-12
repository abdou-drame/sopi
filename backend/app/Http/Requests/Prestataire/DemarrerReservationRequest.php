<?php

namespace App\Http\Requests\Prestataire;

use Illuminate\Foundation\Http\FormRequest;

class DemarrerReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Le code est normalisé (espaces autour retirés, majuscules) avant validation et comparaison. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Z0-9]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Le code de réservation est obligatoire.',
            'code.string' => 'Le code de réservation doit être un texte.',
            'code.regex' => 'Le code de réservation doit contenir 6 caractères alphanumériques.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'code' => ['description' => 'Code de réservation communiqué par le client : 6 caractères alphanumériques (minuscules et espaces autour acceptés).', 'example' => 'AB12CD'],
        ];
    }
}
