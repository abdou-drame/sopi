<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Motif d'une modération, facultatif (conserver). Voir MasquerAvisRequest pour masquer. */
class ModererAvisRequest extends FormRequest
{
    public const MOTIF_MIN = 10;

    public const MOTIF_MAX = 1000;

    public function authorize(): bool
    {
        return true;
    }

    protected function presenceDuMotif(): string
    {
        return 'nullable';
    }

    public function rules(): array
    {
        $presence = $this->presenceDuMotif();

        return ['motif' => [$presence, 'string', 'min:'.self::MOTIF_MIN, 'max:'.self::MOTIF_MAX]];
    }

    public function messages(): array
    {
        return [
            'motif.required' => 'Le motif de la modération est obligatoire.',
            'motif.string' => 'Le motif doit être un texte.',
            'motif.min' => 'Le motif doit contenir au moins '.self::MOTIF_MIN.' caractères.',
            'motif.max' => 'Le motif ne peut pas dépasser '.self::MOTIF_MAX.' caractères.',
        ];
    }

    public function bodyParameters(): array
    {
        return ['motif' => ['description' => 'Motif de la décision ('.self::MOTIF_MIN.' à '.self::MOTIF_MAX.' caractères). Obligatoire pour masquer.', 'example' => 'Propos injurieux sans rapport avec la prestation.', 'required' => false]];
    }

    public function motif(): ?string
    {
        $motif = $this->validated('motif');

        return $motif === null ? null : trim($motif);
    }
}
