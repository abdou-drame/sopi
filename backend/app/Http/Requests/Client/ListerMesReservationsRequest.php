<?php

namespace App\Http\Requests\Client;

use App\Enums\StatutReservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListerMesReservationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['nullable', 'string', Rule::enum(StatutReservation::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'statut.string' => $this->messageStatut(),
            'statut.enum' => $this->messageStatut(),
        ];
    }

    public function queryParameters(): array
    {
        return [
            'statut' => ['description' => 'Filtre par statut : '.$this->valeurs().'. Par défaut : tous.', 'example' => 'en_attente', 'required' => false],
        ];
    }

    public function statut(): ?StatutReservation
    {
        return $this->query('statut') ? StatutReservation::from($this->query('statut')) : null;
    }

    private function valeurs(): string
    {
        return implode(', ', array_column(StatutReservation::cases(), 'value'));
    }

    private function messageStatut(): string
    {
        return 'Le statut doit être l\'un de : '.$this->valeurs().'.';
    }
}
