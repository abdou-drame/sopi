<?php

namespace App\Http\Requests\Profil;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class ChangerMotDePasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mot_de_passe_actuel' => [
                'required', 'string', 'max:255',
                function (string $attribut, mixed $valeur, \Closure $echec) {
                    if (! Hash::check($valeur, $this->user()->password)) {
                        $echec('Le mot de passe actuel est incorrect.');
                    }
                },
            ],
            'nouveau_mot_de_passe' => ['required', 'string', 'min:8', 'max:255'],
            'confirmation' => ['required', 'string', 'same:nouveau_mot_de_passe'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.same' => 'La confirmation ne correspond pas au nouveau mot de passe.',
        ];
    }
}
