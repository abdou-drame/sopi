<?php

namespace App\Http\Requests\Prestataire;

use App\Enums\OperateurRetrait;
use App\Support\TelephoneSenegal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DemanderRetraitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // Un numéro invalide reste tel quel pour que la règle « regex » le rejette.
            'telephone_versement' => TelephoneSenegal::normaliser($this->input('telephone_versement')) ?? $this->input('telephone_versement'),
        ]);
    }

    public function rules(): array
    {
        return [
            // « integer » : un décimal (« 5000.5 ») ou un texte est refusé. Les bornes viennent de la configuration.
            'montant' => ['required', 'integer', 'min:'.$this->minimum(), 'max:'.$this->maximum()],
            'telephone_versement' => ['required', 'string', 'regex:/^\+2217[05678]\d{7}$/'],
            'operateur' => ['required', 'string', Rule::enum(OperateurRetrait::class)],
        ];
    }

    public function messages(): array
    {
        $minimum = number_format($this->minimum(), 0, ',', ' ');
        $maximum = number_format($this->maximum(), 0, ',', ' ');

        return [
            'montant.required' => 'Le montant du retrait est obligatoire.',
            'montant.integer' => 'Le montant doit être un nombre entier de FCFA.',
            'montant.min' => "Le montant minimum d'un retrait est de {$minimum} FCFA.",
            'montant.max' => "Le montant maximum d'un retrait est de {$maximum} FCFA.",
            'telephone_versement.required' => 'Le numéro de versement est obligatoire.',
            'operateur.required' => "L'opérateur du versement est obligatoire (wave ou orange_money).",
            'operateur.enum' => "L'opérateur doit être wave ou orange_money.",
            'telephone_versement.regex' => 'Le numéro de versement doit être un mobile sénégalais valide (ex. : 77 123 45 67).',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'montant' => ['description' => 'Montant à retirer, en FCFA (entier), entre le minimum et le maximum de la configuration.', 'example' => 5000],
            'operateur' => ['description' => 'Opérateur mobile money qui reçoit le versement : `wave` ou `orange_money`.', 'example' => 'wave'],
            'telephone_versement' => ['description' => 'Mobile sénégalais (Wave ou Orange Money) qui reçoit le versement.', 'example' => '771234567'],
        ];
    }

    private function minimum(): int
    {
        return (int) config('sopi.retraits.montant_minimum');
    }

    private function maximum(): int
    {
        return (int) config('sopi.retraits.montant_maximum');
    }
}
