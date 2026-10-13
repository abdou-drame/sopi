<?php

namespace App\Http\Requests\Admin;

use App\Enums\DecisionLitige;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TrancherLitigeRequest extends FormRequest
{
    public const MOTIF_MIN = 10;

    public const MOTIF_MAX = 1000;

    public const DECISIONS = ['rejet', 'avertissement', 'suspension_temporaire', 'bannissement_definitif'];

    public const CIBLES = ['prestataire', 'client'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(self::DECISIONS)],
            'motif' => ['required', 'string', 'min:'.self::MOTIF_MIN, 'max:'.self::MOTIF_MAX],
            'cible' => ['nullable', 'string', Rule::in(self::CIBLES)],
            'dateFin' => ['required_if:decision,suspension_temporaire', 'nullable', 'date', 'after:now'],
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
            'cible.in' => 'La cible doit être : '.implode(', ', self::CIBLES).'.',
            'dateFin.required_if' => 'La date de fin de la suspension est obligatoire.',
            'dateFin.date' => 'La date de fin doit être une date valide.',
            'dateFin.after' => 'La date de fin doit être dans le futur.',
            'motif.max' => 'Le motif ne peut pas dépasser '.self::MOTIF_MAX.' caractères.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'decision' => ['description' => 'Décision : '.implode(', ', self::DECISIONS).'.', 'example' => 'rejet'],
            'motif' => ['description' => 'Motif de la décision, de '.self::MOTIF_MIN.' à '.self::MOTIF_MAX.' caractères. Il sert aussi de motif à la sanction éventuelle.', 'example' => 'Le prestataire a prouvé sa présence sur place.'],
            'cible' => ['description' => 'Compte sanctionné par une décision de sanction : '.implode(', ', self::CIBLES).' (défaut : prestataire).', 'example' => 'prestataire', 'required' => false],
            'dateFin' => ['description' => 'Obligatoire pour une suspension temporaire : fin de la suspension, dans le futur (ISO 8601).', 'example' => '2030-02-01T00:00:00+00:00', 'required' => false],
        ];
    }

    public function decision(): DecisionLitige
    {
        return DecisionLitige::from($this->validated('decision'));
    }

    public function cible(): string
    {
        return $this->validated('cible') ?? 'prestataire';
    }

    public function dateFin(): ?Carbon
    {
        $date = $this->validated('dateFin');

        return $date === null ? null : Carbon::parse($date);
    }

    public function motif(): string
    {
        return trim($this->validated('motif'));
    }
}
