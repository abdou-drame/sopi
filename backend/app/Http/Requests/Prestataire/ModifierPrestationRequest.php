<?php

namespace App\Http\Requests\Prestataire;

use Illuminate\Validation\Validator;

/**
 * Modification partielle d'une prestation : seuls les champs envoyés sont validés et modifiés.
 */
class ModifierPrestationRequest extends CreerPrestationRequest
{
    private const CHAMPS = ['nom', 'duree_minutes', 'prix', 'actif'];

    public function rules(): array
    {
        $regles = array_map(fn (array $r) => ['sometimes', ...$r], parent::rules());
        $regles['actif'] = ['sometimes', 'boolean'];

        return $regles;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->hasAny(self::CHAMPS)) {
                    $validator->errors()->add('nom', 'Indiquez au moins une information à modifier.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'actif.boolean' => 'Le champ actif doit être vrai ou faux.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'nom' => ['description' => 'Nouveau nom (2 à 100 caractères).', 'example' => 'Vidange + filtre', 'required' => false],
            'duree_minutes' => ['description' => 'Nouvelle durée en minutes.', 'example' => 75, 'required' => false],
            'prix' => ['description' => 'Nouveau prix en FCFA, supérieur à 0.', 'example' => 17500, 'required' => false],
            'actif' => ['description' => 'false pour désactiver la prestation (elle n\'est jamais supprimée), true pour la réactiver.', 'example' => false, 'required' => false],
        ];
    }

    /** Données à appliquer : uniquement les champs présents et validés. */
    public function donnees(): array
    {
        return $this->safe()->only(self::CHAMPS);
    }
}
