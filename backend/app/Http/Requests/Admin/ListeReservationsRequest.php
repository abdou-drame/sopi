<?php

namespace App\Http\Requests\Admin;

use App\Enums\StatutReservation;
use App\Http\Requests\Admin\Concerns\FiltresDeListe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Filtres de GET /admin/reservations : statut, prestataire, client, jour du créneau, code. */
class ListeReservationsRequest extends FormRequest
{
    use FiltresDeListe;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['nullable', 'string', Rule::in(array_column(StatutReservation::cases(), 'value'))],
            'prestataire' => ['nullable', 'string', 'max:100'],
            'client' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:20'],
        ] + $this->reglesCommunes();
    }

    public function messages(): array
    {
        return [
            'statut.in' => 'Le statut doit être en_attente, acceptee, confirmee, en_cours, terminee, refusee ou annulee.',
            'prestataire.max' => 'Le nom du prestataire ne peut pas dépasser 100 caractères.',
            'client.max' => 'Le nom du client ne peut pas dépasser 100 caractères.',
            'q.max' => 'La recherche ne peut pas dépasser 20 caractères.',
            'q.string' => 'La recherche doit être un texte.',
            'debut.date_format' => 'La date de début du créneau doit être au format AAAA-MM-JJ.',
            'fin.date_format' => 'La date de fin du créneau doit être au format AAAA-MM-JJ.',
        ] + $this->messagesCommuns();
    }

    public function queryParameters(): array
    {
        return [
            'statut' => ['description' => 'en_attente, acceptee, confirmee, en_cours, terminee, refusee ou annulee.', 'example' => 'confirmee', 'required' => false],
            'prestataire' => ['description' => 'Identifiant du profil prestataire (celui des autres routes d\'administration).', 'example' => '0198a1b2-0000-7000-8000-000000000012', 'required' => false],
            'client' => ['description' => 'Identifiant du compte client.', 'example' => '0198a1b2-0000-7000-8000-000000000001', 'required' => false],
            'debut' => ['description' => 'Créneaux commençant à partir de ce jour (AAAA-MM-JJ, inclus).', 'example' => '2030-01-01', 'required' => false],
            'fin' => ['description' => 'Créneaux commençant jusqu\'à ce jour (AAAA-MM-JJ, inclus).', 'example' => '2030-01-31', 'required' => false],
            'q' => ['description' => 'Code de réservation (recherche partielle, sans tenir compte de la casse). Le code lui-même n\'est jamais renvoyé.', 'example' => 'AB12', 'required' => false],
            'page' => ['description' => 'Numéro de page (20 résultats par page).', 'example' => 1, 'required' => false],
        ];
    }

    /** @return array{statut: ?string, prestataire: ?string, client: ?string, q: ?string, debut: ?Carbon, fin: ?Carbon} `fin` est la borne haute exclue */
    public function filtres(): array
    {
        $v = $this->validated();

        return [
            'statut' => $v['statut'] ?? null,
            'prestataire' => $v['prestataire'] ?? null,
            'client' => $v['client'] ?? null,
            'q' => filled($v['q'] ?? null) ? $v['q'] : null,
            'debut' => $this->debut(),
            'fin' => $this->finExclue(),
        ];
    }
}
