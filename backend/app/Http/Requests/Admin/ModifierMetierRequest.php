<?php

namespace App\Http\Requests\Admin;

use App\Models\Metier;
use App\Services\MetierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Modification d'un métier : libellé et/ou état actif, au moins un des deux. Le code ne change jamais. */
class ModifierMetierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('libelle'))) {
            $this->merge(['libelle' => trim(preg_replace('/\s+/u', ' ', $this->input('libelle')))]);
        }
    }

    public function rules(): array
    {
        return [
            'libelle' => ['sometimes', 'required', 'string', 'min:2', 'max:'.MetierService::LIBELLE_MAX],
            'actif' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validateur) {
            if ($validateur->errors()->isNotEmpty()) {
                return;
            }
            if (! $this->has('libelle') && ! $this->has('actif')) {
                $validateur->errors()->add('libelle', "Indiquez le nouveau nom ou l'état du métier.");

                return;
            }
            if ($this->has('libelle')) {
                $code = MetierService::codeDepuis((string) $this->input('libelle'));
                if ($code === '') {
                    $validateur->errors()->add('libelle', 'Le nom du métier doit contenir des lettres ou des chiffres.');
                } elseif (Metier::query()->where('code', $code)->whereKeyNot((string) $this->route('id'))->exists()) {
                    $validateur->errors()->add('libelle', 'Un métier portant ce nom existe déjà.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'libelle.required' => 'Le nom du métier est obligatoire.',
            'libelle.string' => 'Le nom du métier doit être un texte.',
            'libelle.min' => 'Le nom du métier doit contenir au moins 2 caractères.',
            'libelle.max' => 'Le nom du métier ne doit pas dépasser '.MetierService::LIBELLE_MAX.' caractères.',
            'actif.required' => "L'état du métier est obligatoire.",
            'actif.boolean' => "L'état du métier doit être vrai ou faux.",
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'libelle' => ['description' => 'Nouveau nom affiché (le code ne change pas).', 'example' => 'Plomberie et chauffage', 'required' => false],
            'actif' => ['description' => 'false : plus de nouvelle inscription pour ce métier ; true : de nouveau proposé.', 'example' => true, 'required' => false],
        ];
    }

    public function libelle(): ?string
    {
        return $this->has('libelle') ? $this->validated('libelle') : null;
    }

    public function actif(): ?bool
    {
        return $this->has('actif') ? $this->boolean('actif') : null;
    }
}
