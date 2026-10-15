<?php

namespace App\Http\Requests\Appareil;

use App\Enums\PlateformeAppareil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnregistrerAppareilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jeton' => ['required', 'string', 'min:20', 'max:512'],
            'plateforme' => ['required', 'string', Rule::enum(PlateformeAppareil::class)],
        ];
    }

    public function messages(): array
    {
        return ['plateforme.Illuminate\Validation\Rules\Enum' => 'La plateforme doit être android ou ios.'];
    }
}
