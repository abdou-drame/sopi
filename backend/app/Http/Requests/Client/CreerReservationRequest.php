<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class CreerReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'creneau_id' => ['required', 'uuid'],
            'prestation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'creneau_id.required' => 'Le créneau est obligatoire.',
            'creneau_id.uuid' => 'Le créneau est invalide.',
            'prestation_id.required' => 'La prestation est obligatoire.',
            'prestation_id.uuid' => 'La prestation est invalide.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'creneau_id' => ['description' => 'Identifiant du créneau, que le client a verrouillé.', 'example' => '0198a1b2-0000-7000-8000-000000000040'],
            'prestation_id' => ['description' => 'Identifiant de la prestation, active et proposée par le prestataire du créneau.', 'example' => '0198a1b2-0000-7000-8000-000000000050'],
        ];
    }
}
