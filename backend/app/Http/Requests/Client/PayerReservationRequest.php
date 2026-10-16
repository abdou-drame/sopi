<?php

namespace App\Http\Requests\Client;

use App\Enums\OperateurRetrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayerReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operateur' => ['nullable', 'string', Rule::enum(OperateurRetrait::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'operateur.enum' => 'L\'opérateur doit être « wave » ou « orange_money ».',
        ];
    }

    public function operateur(): ?OperateurRetrait
    {
        return OperateurRetrait::tryFrom((string) $this->input('operateur'));
    }
}
