<?php

namespace App\Http\Requests\Admin;

use App\Enums\StatutCompte;
use App\Http\Requests\Admin\Concerns\FiltresDeListe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Filtres de GET /admin/utilisateurs : rôle, statut du compte, recherche, période de création. */
class ListeUtilisateursRequest extends FormRequest
{
    use FiltresDeListe;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['nullable', 'string', Rule::in(['client', 'prestataire'])],
            'statut' => ['nullable', 'string', Rule::in(array_column(StatutCompte::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:100'],
        ] + $this->reglesCommunes();
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Le rôle doit être client ou prestataire.',
            'statut.in' => 'Le statut doit être en_attente, actif, refuse, suspendu ou banni.',
            'q.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'q.string' => 'La recherche doit être un texte.',
        ] + $this->messagesCommuns();
    }

    public function queryParameters(): array
    {
        return [
            'role' => ['description' => 'client ou prestataire (les administrateurs ne sont jamais listés).', 'example' => 'prestataire', 'required' => false],
            'statut' => ['description' => 'Statut du compte : en_attente, actif, refuse, suspendu ou banni.', 'example' => 'actif', 'required' => false],
            'q' => ['description' => 'Recherche partielle, sans tenir compte des majuscules, dans le nom, le prénom, l\'e-mail, le téléphone et le nom d\'entreprise.', 'example' => 'diop', 'required' => false],
            'debut' => ['description' => 'Comptes créés à partir de ce jour (AAAA-MM-JJ, inclus).', 'example' => '2030-01-01', 'required' => false],
            'fin' => ['description' => 'Comptes créés jusqu\'à ce jour (AAAA-MM-JJ, inclus).', 'example' => '2030-01-31', 'required' => false],
            'page' => ['description' => 'Numéro de page (20 résultats par page).', 'example' => 1, 'required' => false],
        ];
    }

    /** @return array{role: ?string, statut: ?string, q: ?string, debut: ?Carbon, fin: ?Carbon} `fin` est la borne haute exclue */
    public function filtres(): array
    {
        $v = $this->validated();

        return [
            'role' => $v['role'] ?? null,
            'statut' => $v['statut'] ?? null,
            'q' => filled($v['q'] ?? null) ? $v['q'] : null,
            'debut' => $this->debut(),
            'fin' => $this->finExclue(),
        ];
    }
}
