<?php

namespace App\Http\Requests\Prestataire;

use App\Services\AgendaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConfigurerAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plages' => ['required', 'array', 'min:1', 'max:'.AgendaService::PLAGES_MAX],
            'plages.*' => ['array'],
            'plages.*.date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'plages.*.heure_debut' => ['required', 'date_format:H:i'],
            'plages.*.heure_fin' => ['required', 'date_format:H:i'],
            'plages.*.duree_creneau_minutes' => ['required', 'integer', 'min:'.AgendaService::DUREE_MIN_MINUTES, 'max:'.AgendaService::DUREE_MAX_MINUTES],
            'plages.*.repeter_semaines' => ['nullable', 'integer', 'min:0', 'max:'.AgendaService::SEMAINES_MAX],
        ];
    }

    /** Règles qui croisent plusieurs champs d'une même plage, puis le total de la requête. */
    public function after(): array
    {
        return [function (Validator $validateur) {
            $plages = $this->input('plages');
            if ($validateur->errors()->has('plages') || ! is_array($plages)) {
                return;
            }

            $total = 0;
            foreach (array_values($plages) as $i => $plage) {
                if ($this->plageEnErreur($validateur, $i)) {
                    continue;
                }

                $debut = $this->minutes($plage['heure_debut']);
                $fin = $this->minutes($plage['heure_fin']);
                $duree = (int) $plage['duree_creneau_minutes'];
                $numero = $i + 1;

                if ($fin <= $debut) {
                    $validateur->errors()->add("plages.$i.heure_fin", "Plage $numero : l'heure de fin doit être après l'heure de début.");

                    continue;
                }
                if (($fin - $debut) % $duree !== 0) {
                    $validateur->errors()->add("plages.$i.duree_creneau_minutes", "Plage $numero : la plage de ".($fin - $debut)." minutes doit contenir un nombre entier de créneaux de $duree minutes.");

                    continue;
                }

                $total += intdiv($fin - $debut, $duree) * (1 + (int) ($plage['repeter_semaines'] ?? 0));
            }

            if ($total > AgendaService::CRENEAUX_MAX) {
                $validateur->errors()->add('plages', "Cette demande créerait $total créneaux ; le maximum est de ".AgendaService::CRENEAUX_MAX.' par requête.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'plages.required' => 'Indiquez au moins une plage.',
            'plages.array' => 'Les plages doivent être une liste.',
            'plages.min' => 'Indiquez au moins une plage.',
            'plages.max' => 'Vous pouvez envoyer '.AgendaService::PLAGES_MAX.' plages au maximum par requête.',
            'plages.*.array' => 'Chaque plage doit être un objet.',
            'plages.*.date.required' => 'La date de la plage est obligatoire.',
            'plages.*.date.date_format' => 'La date doit être au format AAAA-MM-JJ.',
            'plages.*.date.after_or_equal' => 'La date ne peut pas être dans le passé.',
            'plages.*.heure_debut.required' => 'L\'heure de début est obligatoire.',
            'plages.*.heure_debut.date_format' => 'L\'heure de début doit être au format HH:MM.',
            'plages.*.heure_fin.required' => 'L\'heure de fin est obligatoire.',
            'plages.*.heure_fin.date_format' => 'L\'heure de fin doit être au format HH:MM.',
            'plages.*.duree_creneau_minutes.required' => 'La durée d\'un créneau est obligatoire.',
            'plages.*.duree_creneau_minutes.integer' => 'La durée d\'un créneau doit être un nombre entier de minutes.',
            'plages.*.duree_creneau_minutes.min' => 'Un créneau dure au moins '.AgendaService::DUREE_MIN_MINUTES.' minutes.',
            'plages.*.duree_creneau_minutes.max' => 'Un créneau dure au plus '.AgendaService::DUREE_MAX_MINUTES.' minutes.',
            'plages.*.repeter_semaines.integer' => 'Le nombre de semaines à répéter doit être un nombre entier.',
            'plages.*.repeter_semaines.min' => 'Le nombre de semaines à répéter ne peut pas être négatif.',
            'plages.*.repeter_semaines.max' => 'Vous pouvez répéter une plage sur '.AgendaService::SEMAINES_MAX.' semaines au maximum.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'plages' => ['description' => 'De 1 à '.AgendaService::PLAGES_MAX.' plages.'],
            'plages.*.date' => ['description' => 'Date (AAAA-MM-JJ), aujourd\'hui ou plus tard.', 'example' => '2030-01-15'],
            'plages.*.heure_debut' => ['description' => 'Heure de début (HH:MM).', 'example' => '09:00'],
            'plages.*.heure_fin' => ['description' => 'Heure de fin (HH:MM), après l\'heure de début. La plage doit contenir un nombre entier de créneaux.', 'example' => '12:00'],
            'plages.*.duree_creneau_minutes' => ['description' => 'Durée d\'un créneau, de '.AgendaService::DUREE_MIN_MINUTES.' à '.AgendaService::DUREE_MAX_MINUTES.' minutes.', 'example' => 60],
            'plages.*.repeter_semaines' => ['description' => 'Nombre de semaines supplémentaires où répéter la plage (0 à '.AgendaService::SEMAINES_MAX.', 0 par défaut).', 'example' => 2, 'required' => false],
        ];
    }

    private function plageEnErreur(Validator $validateur, int $i): bool
    {
        foreach ($validateur->errors()->keys() as $cle) {
            if (str_starts_with($cle, "plages.$i.")) {
                return true;
            }
        }

        return false;
    }

    private function minutes(string $heure): int
    {
        [$h, $m] = explode(':', $heure);

        return (int) $h * 60 + (int) $m;
    }
}
