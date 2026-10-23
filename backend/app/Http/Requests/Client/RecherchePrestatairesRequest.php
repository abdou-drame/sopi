<?php

namespace App\Http\Requests\Client;

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
            'metier' => ['nullable', 'string', Rule::exists('metiers', 'code')],
            'quartier' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'prix_min' => ['nullable', 'numeric', 'min:0', 'max:'.self::PRIX_MAX, 'decimal:0,2'],
            'prix_max' => ['nullable', 'numeric', 'min:0', 'max:'.self::PRIX_MAX, 'decimal:0,2', Rule::when($this->filled('prix_min'), ['gte:prix_min'])],
            'note_min' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'disponible' => ['nullable', 'boolean'],
            'tri' => ['nullable', Rule::in(['nom', 'prix_asc', 'prix_desc', 'note'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'metier.exists' => "Ce métier n'existe pas.",
            'metier.string' => 'Le métier doit être un texte.',
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
            'note_min.numeric' => 'La note minimale doit être un nombre.',
            'note_min.min' => 'La note minimale ne peut pas être négative.',
            'note_min.max' => 'La note minimale ne peut pas dépasser 5.',
            'disponible.boolean' => 'Le filtre de disponibilité doit être vrai ou faux.',
            'tri.in' => 'Le tri doit être nom, prix_asc, prix_desc ou note.',
            'page.integer' => 'La page doit être un nombre entier.',
            'page.min' => 'La page doit être au moins 1.',
        ];
    }

    public function queryParameters(): array
    {
        return [
            'metier' => ['description' => "Code d'un métier de la plateforme (ex. : mecanique, coiffure, pressing).", 'example' => 'mecanique', 'required' => false],
            'quartier' => ['description' => 'Quartier (égalité exacte, sans tenir compte de la casse ni des espaces superflus).', 'example' => 'Malika', 'required' => false],
            'q' => ['description' => 'Texte contenu dans le nom de l\'entreprise ou dans le nom d\'une prestation active (sans tenir compte de la casse ; les accents comptent).', 'example' => 'vidange', 'required' => false],
            'prix_min' => ['description' => 'Le prestataire a une prestation active à ce prix ou plus (FCFA).', 'example' => 5000, 'required' => false],
            'prix_max' => ['description' => 'Le prestataire a une prestation active à ce prix ou moins (FCFA), et ≥ prix_min.', 'example' => 20000, 'required' => false],
            'note_min' => ['description' => 'Note moyenne minimale (0 à 5) ; exclut les prestataires sans avis.', 'example' => 4, 'required' => false],
            'disponible' => ['description' => 'true : ne garde que les prestataires ayant au moins un créneau libre à venir.', 'example' => true, 'required' => false],
            'tri' => ['description' => 'nom (défaut), prix_asc ou prix_desc (selon le prix minimal des prestations actives), ou note (note moyenne décroissante, prestataires sans avis en dernier).', 'example' => 'note', 'required' => false],
            'page' => ['description' => 'Numéro de page (20 résultats par page).', 'example' => 1, 'required' => false],
        ];
    }

    /** Filtres validés, prêts pour le service. */
    public function filtres(): array
    {
        return $this->safe()->only(['metier', 'quartier', 'q', 'prix_min', 'prix_max', 'note_min', 'tri']) + ['disponible' => $this->boolean('disponible')];
    }
}
