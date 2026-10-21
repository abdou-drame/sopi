<?php

namespace App\Http\Requests\Admin;

use App\Enums\StatutRetrait;
use App\Http\Requests\Admin\Concerns\FiltresDeListe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtres de GET /admin/retraits : statut, prestataire, retraits bloqués. */
class ListeRetraitsRequest extends FormRequest
{
    use FiltresDeListe;

    public function authorize(): bool
    {
        return true;
    }

    protected function booleens(): array
    {
        return ['bloques'];
    }

    public function rules(): array
    {
        return [
            'statut' => ['nullable', 'string', Rule::in(array_column(StatutRetrait::cases(), 'value'))],
            'prestataire' => ['nullable', 'string', 'max:100'],
            'bloques' => ['nullable', 'boolean'],
        ] + $this->reglesCommunes(false);
    }

    public function messages(): array
    {
        return [
            'statut.in' => 'Le statut doit être en_cours, reussi ou echoue.',
            'prestataire.max' => 'Le nom du prestataire ne peut pas dépasser 100 caractères.',
            'bloques.boolean' => 'Le filtre « bloques » doit être vrai ou faux.',
        ] + $this->messagesCommuns();
    }

    public function queryParameters(): array
    {
        return [
            'statut' => ['description' => 'en_cours, reussi ou echoue.', 'example' => 'en_cours', 'required' => false],
            'prestataire' => ['description' => 'Identifiant du profil prestataire.', 'example' => '0198a1b2-0000-7000-8000-000000000012', 'required' => false],
            'bloques' => ['description' => 'true : retraits en_cours depuis plus longtemps que le délai de blocage (point d\'arrivée de l\'alerte `retraits_bloques`) ; false : tous les autres.', 'example' => true, 'required' => false],
            'page' => ['description' => 'Numéro de page (20 résultats par page).', 'example' => 1, 'required' => false],
        ];
    }

    /** @return array{statut: ?string, prestataire: ?string, bloques: ?bool} */
    public function filtres(): array
    {
        $v = $this->validated();

        return [
            'statut' => $v['statut'] ?? null,
            'prestataire' => $v['prestataire'] ?? null,
            'bloques' => $this->booleen('bloques'),
        ];
    }
}
