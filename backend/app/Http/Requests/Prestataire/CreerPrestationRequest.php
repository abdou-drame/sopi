<?php

namespace App\Http\Requests\Prestataire;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une prestation. Le domaine n'est pas un champ : il est déduit du métier du prestataire.
 */
class CreerPrestationRequest extends FormRequest
{
    /** Bornes techniques par défaut (à ajuster si besoin) : 24 h max, et le plafond de decimal(12,2). */
    public const DUREE_MAX_MINUTES = 1440;

    public const PRIX_MAX = 9999999999.99;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nom'))) {
            $this->merge(['nom' => trim(preg_replace('/\s+/u', ' ', $this->input('nom')))]);
        }
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:100'],
            'duree_minutes' => ['required', 'integer', 'min:1', 'max:'.self::DUREE_MAX_MINUTES],
            'prix' => ['required', 'numeric', 'gt:0', 'max:'.self::PRIX_MAX, 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de la prestation est obligatoire.',
            'nom.string' => 'Le nom de la prestation doit être un texte.',
            'nom.min' => 'Le nom de la prestation doit contenir au moins 2 caractères.',
            'nom.max' => 'Le nom de la prestation ne doit pas dépasser 100 caractères.',
            'duree_minutes.required' => 'La durée est obligatoire.',
            'duree_minutes.integer' => 'La durée doit être un nombre entier de minutes.',
            'duree_minutes.min' => 'La durée doit être d\'au moins 1 minute.',
            'duree_minutes.max' => 'La durée ne doit pas dépasser '.self::DUREE_MAX_MINUTES.' minutes.',
            'prix.required' => 'Le prix est obligatoire.',
            'prix.numeric' => 'Le prix doit être un nombre.',
            'prix.gt' => 'Le prix doit être supérieur à 0.',
            'prix.max' => 'Le prix est trop élevé.',
            'prix.decimal' => 'Le prix ne doit pas avoir plus de 2 décimales.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'nom' => ['description' => 'Nom de la prestation (2 à 100 caractères, unique chez le prestataire sans tenir compte de la casse).', 'example' => 'Vidange complète'],
            'duree_minutes' => ['description' => 'Durée estimée en minutes (1 à '.self::DUREE_MAX_MINUTES.').', 'example' => 60],
            'prix' => ['description' => 'Prix en FCFA (XOF), supérieur à 0, 2 décimales au plus.', 'example' => 15000],
        ];
    }
}
