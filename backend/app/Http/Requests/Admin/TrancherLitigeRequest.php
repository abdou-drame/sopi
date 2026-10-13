<?php

namespace App\Http\Requests\Admin;

use App\Enums\DecisionLitige;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrancherLitigeRequest extends FormRequest
{
    public const MOTIF_MIN = 10;

    public const MOTIF_MAX = 1000;

    /** Décisions acceptées. Les décisions de sanction s'ajoutent avec les sanctions (M9.3). */
    public const DECISIONS = ['rejet'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(self::DECISIONS)],
            'motif' => ['required', 'string', 'min:'.self::MOTIF_MIN, 'max:'.self::MOTIF_MAX],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est obligatoire.',
            'decision.in' => 'La décision doit être : '.implode(', ', self::DECISIONS).'.',
            'motif.required' => 'Le motif de la décision est obligatoire.',
            'motif.string' => 'Le motif doit être un texte.',
            'motif.min' => 'Le motif doit contenir au moins '.self::MOTIF_MIN.' caractères.',
            'motif.max' => 'Le motif ne peut pas dépasser '.self::MOTIF_MAX.' caractères.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'decision' => ['description' => 'Décision : '.implode(', ', self::DECISIONS).'.', 'example' => 'rejet'],
            'motif' => ['description' => 'Motif de la décision, de '.self::MOTIF_MIN.' à '.self::MOTIF_MAX.' caractères.', 'example' => 'Le prestataire a prouvé sa présence sur place.'],
        ];
    }

    public function decision(): DecisionLitige
    {
        return DecisionLitige::from($this->validated('decision'));
    }

    public function motif(): string
    {
        return trim($this->validated('motif'));
    }
}
