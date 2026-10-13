<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class DeposerAvisRequest extends FormRequest
{
    public const COMMENTAIRE_MIN = 3;

    public const COMMENTAIRE_MAX = 1000;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Commentaire vide ou fait d'espaces = pas de commentaire.
        if (is_string($this->input('commentaire')) && trim($this->input('commentaire')) === '') {
            $this->merge(['commentaire' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['nullable', 'string', 'min:'.self::COMMENTAIRE_MIN, 'max:'.self::COMMENTAIRE_MAX],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'La note est obligatoire.',
            'note.integer' => 'La note doit être un nombre entier.',
            'note.between' => 'La note doit être comprise entre 1 et 5.',
            'commentaire.string' => 'Le commentaire doit être un texte.',
            'commentaire.min' => 'Le commentaire doit contenir au moins '.self::COMMENTAIRE_MIN.' caractères.',
            'commentaire.max' => 'Le commentaire ne peut pas dépasser '.self::COMMENTAIRE_MAX.' caractères.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'note' => ['description' => 'Note entière de 1 (très mauvais) à 5 (excellent).', 'example' => 4],
            'commentaire' => ['description' => 'Commentaire facultatif, de '.self::COMMENTAIRE_MIN.' à '.self::COMMENTAIRE_MAX.' caractères.', 'example' => 'Travail soigné et ponctuel.', 'required' => false],
        ];
    }

    /** Commentaire nettoyé (espaces de début et de fin retirés), ou null. */
    public function commentaire(): ?string
    {
        $commentaire = $this->validated('commentaire');

        return $commentaire === null ? null : trim($commentaire);
    }
}
