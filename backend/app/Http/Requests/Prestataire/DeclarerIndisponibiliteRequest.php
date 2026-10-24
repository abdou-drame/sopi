<?php

namespace App\Http\Requests\Prestataire;

use App\Services\IndisponibiliteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class DeclarerIndisponibiliteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'debut' => ['required', 'date_format:Y-m-d H:i'],
            'fin' => ['required', 'date_format:Y-m-d H:i'],
            'motif' => ['nullable', 'string', 'max:255'],
            'annuler_reservations' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validateur) {
            if ($validateur->errors()->isNotEmpty()) {
                return;
            }

            [$debut, $fin] = $this->plage();
            if ($fin->lte($debut)) {
                $validateur->errors()->add('fin', 'La fin doit être après le début.');
            } elseif ($fin->lte(now())) {
                $validateur->errors()->add('fin', 'La fin doit être dans le futur.');
            } elseif ($debut->diffInMinutes($fin) > IndisponibiliteService::DUREE_MAX_JOURS * 1440) {
                $validateur->errors()->add('fin', 'La plage ne peut pas dépasser '.IndisponibiliteService::DUREE_MAX_JOURS.' jours.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'debut.required' => 'Le début est obligatoire.',
            'debut.date_format' => 'Le début doit être au format AAAA-MM-JJ HH:MM.',
            'fin.required' => 'La fin est obligatoire.',
            'fin.date_format' => 'La fin doit être au format AAAA-MM-JJ HH:MM.',
            'motif.max' => 'Le motif ne peut pas dépasser 255 caractères.',
            'annuler_reservations.boolean' => 'Le choix d\'annuler les réservations doit être vrai ou faux.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'debut' => ['description' => 'Début (AAAA-MM-JJ HH:MM, fuseau Africa/Dakar). Peut être passé ou présent.', 'example' => '2030-01-15 08:00'],
            'fin' => ['description' => 'Fin (AAAA-MM-JJ HH:MM), après le début et dans le futur. Plage de '.IndisponibiliteService::DUREE_MAX_JOURS.' jours au maximum.', 'example' => '2030-01-20 18:00'],
            'motif' => ['description' => 'Motif (congé, urgence...). Facultatif, non conservé.', 'example' => 'Congé', 'required' => false],
            'annuler_reservations' => ['description' => 'true (défaut) : les réservations de la plage sont annulées et remboursées. false : seuls les créneaux libres sont bloqués, les réservations restent inchangées.', 'example' => true, 'required' => false],
        ];
    }

    /** Annuler (et rembourser) les réservations de la plage ? Vrai par défaut. */
    public function annulerReservations(): bool
    {
        return $this->boolean('annuler_reservations', true);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function plage(): array
    {
        $fuseau = config('app.timezone');

        return [
            Carbon::createFromFormat('Y-m-d H:i', $this->input('debut'), $fuseau),
            Carbon::createFromFormat('Y-m-d H:i', $this->input('fin'), $fuseau),
        ];
    }
}
