<?php

namespace App\Http\Requests\Prestataire;

use App\Enums\GroupeReservation;
use App\Enums\StatutReservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListerReservationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['nullable', 'string', Rule::enum(StatutReservation::class)],
            'groupe' => ['nullable', 'string', Rule::enum(GroupeReservation::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'statut.string' => $this->messageStatut(),
            'statut.enum' => $this->messageStatut(),
            'groupe.string' => $this->messageGroupe(),
            'groupe.enum' => $this->messageGroupe(),
        ];
    }

    public function queryParameters(): array
    {
        return [
            'statut' => ['description' => 'Filtre par statut : '.implode(', ', array_column(StatutReservation::cases(), 'value')).'. Par défaut : tous.', 'example' => 'en_attente', 'required' => false],
            'groupe' => ['description' => 'Onglet : a_venir (en attente, acceptée, confirmée ou en cours, créneau non terminé ; date de créneau croissante), terminees ou annulees (annulée ou refusée) ; les deux derniers du plus récent au plus ancien. Combinable avec statut.', 'example' => 'a_venir', 'required' => false],
        ];
    }

    public function groupe(): ?GroupeReservation
    {
        return $this->query('groupe') ? GroupeReservation::from($this->query('groupe')) : null;
    }

    private function messageGroupe(): string
    {
        return 'Le groupe doit être l\'un de : a_venir, terminees, annulees.';
    }

    public function statut(): ?StatutReservation
    {
        return $this->query('statut') ? StatutReservation::from($this->query('statut')) : null;
    }

    private function messageStatut(): string
    {
        return 'Le statut doit être l\'un de : '.implode(', ', array_column(StatutReservation::cases(), 'value')).'.';
    }
}
