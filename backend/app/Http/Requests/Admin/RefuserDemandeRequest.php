<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RefuserDemandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $motif = $this->input('motif');
        $this->merge(['motif' => is_string($motif) && trim($motif) !== '' ? trim($motif) : null]);
    }

    public function rules(): array
    {
        return [
            // Motif global du dossier : obligatoire seulement si aucun document n'est refusé avec son propre motif.
            'motif' => ['nullable', 'required_without:justificatifs', 'string', 'min:10', 'max:500'],
            // Refus document par document (mémoire, tableau 2.8) : les pièces non listées sont validées.
            'justificatifs' => ['nullable', 'required_without:motif', 'array', 'min:1', 'max:10'],
            'justificatifs.*.id' => ['required', 'uuid', 'distinct'],
            'justificatifs.*.motif' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motif.required' => 'Le motif du refus est obligatoire.',
            'motif.string' => 'Le motif du refus doit être un texte.',
            'motif.min' => 'Le motif du refus doit contenir au moins 10 caractères.',
            'motif.max' => 'Le motif du refus ne doit pas dépasser 500 caractères.',
            'motif.required_without' => 'Le motif du refus est obligatoire.',
            'justificatifs.required_without' => 'Indiquez le motif du refus ou refusez au moins un document avec son motif.',
            'justificatifs.*.id.required' => 'Le document refusé doit être précisé.',
            'justificatifs.*.id.uuid' => 'Le document refusé est invalide.',
            'justificatifs.*.id.distinct' => 'Un document ne peut être refusé qu\'une seule fois.',
            'justificatifs.*.motif.required' => 'Le motif du refus de chaque document est obligatoire.',
            'justificatifs.*.motif.min' => 'Le motif du refus d\'un document doit contenir au moins 10 caractères.',
            'justificatifs.*.motif.max' => 'Le motif du refus d\'un document ne doit pas dépasser 500 caractères.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'motif' => ['description' => 'Raison globale du refus, communiquée au prestataire (10 à 500 caractères). Facultative si au moins un document est refusé avec son motif.', 'example' => 'La photocopie de la CNI est illisible.', 'required' => false],
            'justificatifs' => ['description' => 'Documents refusés, chacun avec son motif. Les autres documents du dossier sont validés. Sans cette liste, tous les documents sont rejetés.', 'required' => false],
            'justificatifs.*.id' => ['description' => 'Identifiant du justificatif refusé (il doit appartenir à ce dossier).', 'example' => '0198a1b2-0000-7000-8000-000000000010'],
            'justificatifs.*.motif' => ['description' => 'Motif du refus de ce document (10 à 500 caractères).', 'example' => 'Photo floue : le numéro est illisible.'],
        ];
    }
}
