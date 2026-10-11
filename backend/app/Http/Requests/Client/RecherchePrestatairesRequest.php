<?php

namespace App\Http\Requests\Client;

use App\Enums\Metier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecherchePrestatairesRequest extends FormRequest
{
    private const PRIX_MAX = 9999999999.99;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['quartier', 'q'] as $champ) {
            if (is_string($this->query($champ))) {
                $this->query->set($champ, trim(preg_replace('/\s+/u', ' ', $this->query($champ))));
            }
        }
    }

    public function rules(): array
    {
        return [
            'metier' => ['nullable', 'string', Rule::enum(Metier::class)],
            'quartier' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'prix_min' => ['nullable', 'numeric', 'min:0', 'max:'.self::PRIX_MAX, 'decimal:0,2'],
            'prix_max' => ['nullable', 'numeric', 'min:0', 'max:'.self::PRIX_MAX, 'decimal:0,2', Rule::when($this->filled('prix_min'), ['gte:prix_min'])],
            'tri' => ['nullable', Rule::in(['nom', 'prix_asc', 'prix_desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'metier.enum' => 'Le métier doit être mecanique, coiffure ou pressing.',
            'metier.string' => 'Le métier doit être mecanique, coiffure ou pressing.',
            'quartier.string' => 'Le quartier doit être un texte.',
            'quartier.max' => 'Le quartier ne doit pas dépasser 100 caractères.',
            'q.string' => 'Le texte recherché doit être un texte.',
            'q.max' => 'Le texte recherché ne doit pas dépasser 100 caractères.',
            'prix_min.numeric' => 'Le prix minimum doit être un nombre.',
            'prix_min.min' => 'Le prix minimum ne peut pas être négatif.',
            'prix_min.max' => 'Le prix minimum est trop élevé.',
            'prix_min.decimal' => 'Le prix minimum ne doit pas avoir plus de 2 décimales.',
            'prix_max.numeric' => 'Le prix maximum doit être un nombre.',
            'prix_max.min' => 'Le prix maximum ne peut pas être négatif.',
            'prix_max.max' => 'Le prix maximum est trop élevé.',
            'prix_max.decimal' => 'Le prix maximum ne doit pas avoir plus de 2 décimales.',
            'prix_max.gte' => 'Le prix maximum doit être supérieur ou égal au prix minimum.',
            'tri.in' => 'Le tri doit être nom, prix_asc ou prix_desc.',
            'page.integer' => 'La page doit être un nombre entier.',
            'page.min' => 'La page doit être au moins 1.',
        ];
    }

    public function queryParameters(): array
    {
        return [
            'metier' => ['description' => 'Métier : mecanique, coiffure ou pressing.', 'example' => 'mecanique', 'required' => false],
            'quartier' => ['description' => 'Quartier (égalité exacte, sans tenir compte de la casse ni des espaces superflus).', 'example' => 'Malika', 'required' => false],
            'q' => ['description' => 'Texte contenu dans le nom de l\'entreprise ou dans le nom d\'une prestation active (sans tenir compte de la casse ; les accents comptent).', 'example' => 'vidange', 'required' => false],
            'prix_min' => ['description' => 'Le prestataire a une prestation active à ce prix ou plus (FCFA).', 'example' => 5000, 'required' => false],
            'prix_max' => ['description' => 'Le prestataire a une prestation active à ce prix ou moins (FCFA), et ≥ prix_min.', 'example' => 20000, 'required' => false],
            'tri' => ['description' => 'nom (défaut), prix_asc ou prix_desc (selon le prix minimal des prestations actives).', 'example' => 'prix_asc', 'required' => false],
            'page' => ['description' => 'Numéro de page (20 résultats par page).', 'example' => 1, 'required' => false],
        ];
    }

    /** Filtres validés, prêts pour le service. */
    public function filtres(): array
    {
        return $this->safe()->only(['metier', 'quartier', 'q', 'prix_min', 'prix_max', 'tri']);
    }
}
