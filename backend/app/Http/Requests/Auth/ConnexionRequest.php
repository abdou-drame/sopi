<?php

namespace App\Http\Requests\Auth;

use App\Support\TelephoneSenegal;
use Illuminate\Foundation\Http\FormRequest;

class ConnexionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Identifiant unique : une adresse e-mail (détectée par la présence d'un « @ ») ou un numéro de téléphone. L'ancien champ
     * « telephone » reste accepté. L'e-mail est mis en minuscules et sans espaces, le numéro est normalisé (+221XXXXXXXXX), pour
     * que la clé de blocage et la recherche soient identiques quelle que soit la façon de saisir.
     */
    protected function prepareForValidation(): void
    {
        $brut = $this->input('identifiant', $this->input('telephone'));
        $identifiant = $brut;

        if (is_string($brut)) {
            $identifiant = str_contains($brut, '@')
                ? mb_strtolower(trim($brut))
                : (TelephoneSenegal::normaliser($brut) ?? trim($brut));
        }

        $this->merge(['identifiant' => $identifiant]);
    }

    public function rules(): array
    {
        // Pas de règle de format ni d'existence : un identifiant erroné et un mot de passe
        // erroné doivent produire exactement la même réponse.
        return [
            'identifiant' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['identifiant.required' => 'Saisissez votre adresse e-mail ou votre numéro de téléphone.'];
    }

    public function bodyParameters(): array
    {
        return [
            'identifiant' => ['description' => 'Adresse e-mail ou numéro de téléphone mobile sénégalais. Le champ « telephone » reste accepté à la place.', 'example' => 'awa@example.com'],
            'password' => ['description' => 'Mot de passe du compte.', 'example' => 'MotDePasse123'],
        ];
    }
}
