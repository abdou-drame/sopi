<?php

namespace App\Http\Requests\Auth;

use App\Support\TelephoneSenegal;
use Illuminate\Foundation\Http\FormRequest;

class InscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            // Un numéro invalide reste tel quel pour que la règle « regex » le rejette.
            'telephone' => TelephoneSenegal::normaliser($this->input('telephone')) ?? $this->input('telephone'),
            'email' => is_string($email) && trim($email) !== '' ? mb_strtolower(trim($email)) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'regex:/^\+2217[05678]\d{7}$/', 'unique:users,telephone'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'telephone.regex' => 'Le numéro de téléphone doit être un mobile sénégalais valide (ex. : 77 123 45 67).',
        ];
    }
}
