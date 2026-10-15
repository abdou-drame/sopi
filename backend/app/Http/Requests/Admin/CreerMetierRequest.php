<?php

namespace App\Http\Requests\Admin;

use App\Models\Metier;
use App\Services\MetierService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Création d'un métier : seul le libellé est saisi, le code en est déduit et doit rester unique. */
class CreerMetierRequest extends FormRequest
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
            'libelle' => ['required', 'string', 'min:2', 'max:'.MetierService::LIBELLE_MAX, function (string $attribut, mixed $valeur, Closure $echec) {
                $code = MetierService::codeDepuis((string) $valeur);
                if ($code === '') {
                    $echec('Le nom du métier doit contenir des lettres ou des chiffres.');
                } elseif (Metier::query()->where('code', $code)->exists()) {
                    $echec('Un métier portant ce nom existe déjà.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'libelle.required' => 'Le nom du métier est obligatoire.',
            'libelle.string' => 'Le nom du métier doit être un texte.',
            'libelle.min' => 'Le nom du métier doit contenir au moins 2 caractères.',
            'libelle.max' => 'Le nom du métier ne doit pas dépasser '.MetierService::LIBELLE_MAX.' caractères.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'libelle' => ['description' => 'Nom affiché du métier (2 à 60 caractères). Le code en est déduit : minuscules, sans accent ni espace.', 'example' => 'Plomberie'],
        ];
    }

    public function libelle(): string
    {
        return $this->validated('libelle');
    }
}
