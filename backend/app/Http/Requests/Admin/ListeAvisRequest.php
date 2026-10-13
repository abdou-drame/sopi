<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListeAvisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prestataire' => ['nullable', 'uuid'],
            'note' => ['nullable', 'integer', 'between:1,5'],
            'masque' => ['nullable', 'boolean'],
            'signale' => ['nullable', 'boolean'],
            'aTraiter' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'prestataire.uuid' => 'Le prestataire doit être un identifiant valide.',
            'note.integer' => 'La note doit être un entier de 1 à 5.',
            'note.between' => 'La note doit être comprise entre 1 et 5.',
            'masque.boolean' => 'Le filtre « masque » doit être vrai ou faux.',
            'signale.boolean' => 'Le filtre « signale » doit être vrai ou faux.',
            'aTraiter.boolean' => 'Le filtre « aTraiter » doit être vrai ou faux.',
            'page.integer' => 'La page doit être un entier.',
            'page.min' => 'La page doit être au moins 1.',
        ];
    }

    public function queryParameters(): array
    {
        return [
            'prestataire' => ['description' => 'Identifiant du prestataire noté.', 'example' => '0198a1b2-0000-7000-8000-000000000002', 'required' => false],
            'note' => ['description' => 'Note de 1 à 5.', 'example' => 2, 'required' => false],
            'masque' => ['description' => 'true : avis masqués ; false : avis affichés ; absent : tous.', 'example' => false, 'required' => false],
            'signale' => ['description' => 'true : avis signalés par le prestataire ; false : jamais signalés.', 'example' => true, 'required' => false],
            'aTraiter' => ['description' => 'true : avis signalés et pas encore modérés.', 'example' => true, 'required' => false],
            'page' => ['description' => 'Numéro de page (20 résultats par page).', 'example' => 1, 'required' => false],
        ];
    }

    /** @return array{prestataire?: ?string, note?: ?int, masque?: ?bool, signale?: ?bool, atraiter?: ?bool} */
    public function filtres(): array
    {
        $v = $this->validated();

        return array_filter([
            'prestataire' => $v['prestataire'] ?? null,
            'note' => isset($v['note']) ? (int) $v['note'] : null,
            'masque' => isset($v['masque']) ? $this->boolean('masque') : null,
            'signale' => isset($v['signale']) ? $this->boolean('signale') : null,
            'atraiter' => isset($v['aTraiter']) ? $this->boolean('aTraiter') : null,
        ], fn ($valeur) => $valeur !== null);
    }
}
