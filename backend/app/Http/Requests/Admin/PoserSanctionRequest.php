<?php

namespace App\Http\Requests\Admin;

use App\Enums\TypeSanction;
use App\Models\Avis;
use App\Models\Litige;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PoserSanctionRequest extends FormRequest
{
    public const MOTIF_MIN = 10;

    public const MOTIF_MAX = 1000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_column(TypeSanction::cases(), 'value'))],
            'motif' => ['required', 'string', 'min:'.self::MOTIF_MIN, 'max:'.self::MOTIF_MAX],
            'dateFin' => ['required_if:type,suspension_temporaire', 'nullable', 'date', 'after:now'],
            'litigeId' => ['nullable', 'uuid'],
            'avisId' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type de sanction est obligatoire.',
            'type.in' => 'Le type doit être : '.implode(', ', array_column(TypeSanction::cases(), 'value')).'.',
            'motif.required' => 'Le motif de la sanction est obligatoire.',
            'motif.string' => 'Le motif doit être un texte.',
            'motif.min' => 'Le motif doit contenir au moins '.self::MOTIF_MIN.' caractères.',
            'motif.max' => 'Le motif ne peut pas dépasser '.self::MOTIF_MAX.' caractères.',
            'dateFin.required_if' => 'La date de fin de la suspension est obligatoire.',
            'dateFin.date' => 'La date de fin doit être une date valide.',
            'dateFin.after' => 'La date de fin doit être dans le futur.',
            'litigeId.uuid' => 'L\'identifiant du litige est invalide.',
            'avisId.uuid' => 'L\'identifiant de l\'avis est invalide.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'type' => ['description' => 'avertissement, suspension_temporaire ou bannissement_definitif.', 'example' => 'suspension_temporaire'],
            'motif' => ['description' => 'Motif de la sanction, de '.self::MOTIF_MIN.' à '.self::MOTIF_MAX.' caractères. Il est montré à la personne sanctionnée quand elle tente de se connecter.', 'example' => 'Comportement injurieux envers un client.'],
            'dateFin' => ['description' => 'Obligatoire pour une suspension temporaire : fin de la suspension, dans le futur (ISO 8601).', 'example' => '2030-02-01T00:00:00+00:00', 'required' => false],
            'litigeId' => ['description' => 'Litige à l\'origine de la sanction (le compte doit en être une partie). Facultatif.', 'example' => '0198a1b2-0000-7000-8000-000000000090', 'required' => false],
            'avisId' => ['description' => 'Avis à l\'origine de la sanction (le compte doit en être l\'auteur). Facultatif.', 'example' => '0198a1b2-0000-7000-8000-000000000080', 'required' => false],
        ];
    }

    public function type(): TypeSanction
    {
        return TypeSanction::from($this->validated('type'));
    }

    public function motif(): string
    {
        return trim($this->validated('motif'));
    }

    public function dateFin(): ?Carbon
    {
        $date = $this->validated('dateFin');

        return $date === null ? null : Carbon::parse($date);
    }

    public function litige(): ?Litige
    {
        $id = $this->validated('litigeId');

        return $id === null ? null : Litige::query()->find($id);
    }

    public function avis(): ?Avis
    {
        $id = $this->validated('avisId');

        return $id === null ? null : Avis::query()->find($id);
    }
}
