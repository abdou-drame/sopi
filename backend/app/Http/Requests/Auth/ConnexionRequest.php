<?php

namespace App\Http\Requests\Auth;

use App\Support\TelephoneSenegal;
use Illuminate\Foundation\Http\FormRequest;

class ConnexionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'telephone' => TelephoneSenegal::normaliser($this->input('telephone')) ?? $this->input('telephone'),
        ]);
    }

    public function rules(): array
    {
        // Pas de règle de format ni d'existence : un identifiant erroné et un mot de passe
        // erroné doivent produire exactement la même réponse.
        return [
            'telephone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
