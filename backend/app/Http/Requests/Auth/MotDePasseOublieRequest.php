<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class MotDePasseOublieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $this->merge(['email' => is_string($email) ? mb_strtolower(trim($email)) : $email]);
    }

    public function rules(): array
    {
        // Aucune règle d'existence : la réponse ne doit jamais révéler si l'adresse correspond à un compte.
        return ['email' => ['required', 'string', 'email', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'L\'adresse e-mail n\'est pas valide.',
        ];
    }

    public function bodyParameters(): array
    {
        return ['email' => ['description' => 'Adresse e-mail du compte.', 'example' => 'awa@example.com']];
    }
}
