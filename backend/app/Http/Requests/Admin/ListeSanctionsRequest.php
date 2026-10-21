<?php

namespace App\Http\Requests\Admin;

use App\Enums\TypeSanction;
use App\Http\Requests\Admin\Concerns\FiltresDeListe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtres de GET /admin/sanctions : état, type, utilisateur, échéance des suspensions. */
class ListeSanctionsRequest extends FormRequest
{
    use FiltresDeListe;

    public const ECHEANCE_MAX_HEURES = 8760;

    public function authorize(): bool
    {
        return true;
    }

    protected function alias(): array
    {
        return ['echeance_dans_heures' => 'echeanceDansHeures'];
    }

    protected function booleens(): array
    {
        return ['actives'];
    }

    public function rules(): array
    {
        return [
            'actives' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string', Rule::in(array_column(TypeSanction::cases(), 'value'))],
            'utilisateur' => ['nullable', 'string', 'max:100'],
            'echeanceDansHeures' => ['nullable', 'integer', 'between:1,'.self::ECHEANCE_MAX_HEURES],
        ] + $this->reglesCommunes(false);
    }

    public function messages(): array
    {
        return [
            'actives.boolean' => 'Le filtre « actives » doit être vrai ou faux.',
            'type.in' => 'Le type doit être avertissement, suspension_temporaire ou bannissement_definitif.',
            'utilisateur.max' => 'Le nom du compte ne peut pas dépasser 100 caractères.',
            'echeanceDansHeures.integer' => 'L\'échéance doit être un nombre entier d\'heures.',
            'echeanceDansHeures.between' => 'L\'échéance doit être comprise entre 1 et '.self::ECHEANCE_MAX_HEURES.' heures.',
        ] + $this->messagesCommuns();
    }

    public function queryParameters(): array
    {
        return [
            'actives' => ['description' => 'true : sanctions non levées ; false : sanctions levées ; absent : toutes.', 'example' => true, 'required' => false],
            'type' => ['description' => 'avertissement, suspension_temporaire ou bannissement_definitif.', 'example' => 'suspension_temporaire', 'required' => false],
            'utilisateur' => ['description' => 'Identifiant du compte sanctionné.', 'example' => '0198a1b2-0000-7000-8000-000000000002', 'required' => false],
            'echeanceDansHeures' => ['description' => 'Suspensions non levées qui se terminent dans les N prochaines heures (1 à 8760), comme l\'alerte « suspensions à échéance ». Alias : echeance_dans_heures.', 'example' => 48, 'required' => false],
            'page' => ['description' => 'Numéro de page (20 résultats par page).', 'example' => 1, 'required' => false],
        ];
    }

    /** @return array{actives: ?bool, type: ?string, utilisateur: ?string, echeanceDansHeures: ?int} */
    public function filtres(): array
    {
        $v = $this->validated();

        return [
            'actives' => $this->booleen('actives'),
            'type' => $v['type'] ?? null,
            'utilisateur' => $v['utilisateur'] ?? null,
            'echeanceDansHeures' => isset($v['echeanceDansHeures']) ? (int) $v['echeanceDansHeures'] : null,
        ];
    }
}
