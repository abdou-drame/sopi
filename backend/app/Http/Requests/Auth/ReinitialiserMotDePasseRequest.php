<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ReinitialiserMotDePasseRequest extends FormRequest
{
    public const MESSAGE_LIEN_INVALIDE = 'Ce lien est invalide, expiré ou déjà utilisé. Faites une nouvelle demande de réinitialisation.';

    public function authorize(): bool
    {
        return true;
    }

    /** Mêmes règles de mot de passe que l'inscription (8 caractères minimum), plus la confirmation. */
    public static function regles(): array
    {
        return [
            'jeton' => ['required', 'string', 'max:200'],
            'mot_de_passe' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }

    public static function messagesFr(): array
    {
        return [
            'jeton.required' => 'Le lien de réinitialisation est manquant.',
            'mot_de_passe.required' => 'Le mot de passe est obligatoire.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'mot_de_passe.max' => 'Le mot de passe est trop long.',
            'mot_de_passe.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
        ];
    }

    public function rules(): array
    {
        return self::regles();
    }

    public function messages(): array
    {
        return self::messagesFr();
    }

    public function bodyParameters(): array
    {
        return [
            'jeton' => ['description' => 'Jeton contenu dans le lien reçu par e-mail.', 'example' => 'jeton-recu-par-email'],
            'mot_de_passe' => ['description' => 'Nouveau mot de passe (8 caractères minimum).', 'example' => 'nouveau-mot-de-passe'],
            'mot_de_passe_confirmation' => ['description' => 'Même mot de passe, saisi une seconde fois.', 'example' => 'nouveau-mot-de-passe'],
        ];
    }
}
