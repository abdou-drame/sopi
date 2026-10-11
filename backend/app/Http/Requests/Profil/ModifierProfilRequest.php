<?php

namespace App\Http\Requests\Profil;

use App\Support\TelephoneSenegal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Un numéro invalide reste tel quel pour que la règle « regex » le rejette.
        $this->merge([
            'telephone' => TelephoneSenegal::normaliser($this->input('telephone')) ?? $this->input('telephone'),
        ]);
    }

    /** L'e-mail et le rôle ne sont pas validés : ils ne sont jamais lus (seuls les champs validés sont utilisés). */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'telephone' => [
                'required', 'string', 'regex:/^\+2217[05678]\d{7}$/',
                Rule::unique('users', 'telephone')->ignore($this->user()?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'telephone.regex' => 'Le numéro de téléphone doit être un mobile sénégalais valide (ex. : 77 123 45 67).',
        ];
    }
}
