<?php

namespace App\Http\Requests\Admin;

use App\Enums\StatutPaiement;
use App\Http\Requests\Admin\Concerns\FiltresDeListe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtres de GET /admin/paiements : statut, remboursement dû, retard de remboursement, réservation. */
class ListePaiementsRequest extends FormRequest
{
    use FiltresDeListe;

    public const RETARD_MAX_MINUTES = 525600;

    public function authorize(): bool
    {
        return true;
    }

    protected function alias(): array
    {
        return ['remboursement_du' => 'remboursementDu', 'retard_remboursement_minutes' => 'retardRemboursementMinutes'];
    }

    protected function booleens(): array
    {
        return ['remboursementDu'];
    }

    public function rules(): array
    {
        return [
            'statut' => ['nullable', 'string', Rule::in(array_column(StatutPaiement::cases(), 'value'))],
            'remboursementDu' => ['nullable', 'boolean'],
            'retardRemboursementMinutes' => ['nullable', 'integer', 'between:1,'.self::RETARD_MAX_MINUTES],
            'reservation' => ['nullable', 'uuid'],
        ] + $this->reglesCommunes(false);
    }

    public function messages(): array
    {
        return [
            'statut.in' => 'Le statut doit être en_attente, reussi, echoue ou rembourse.',
            'remboursementDu.boolean' => 'Le filtre « remboursementDu » doit être vrai ou faux.',
            'retardRemboursementMinutes.integer' => 'Le retard doit être un nombre entier de minutes.',
            'retardRemboursementMinutes.between' => 'Le retard doit être compris entre 1 et '.self::RETARD_MAX_MINUTES.' minutes.',
            'reservation.uuid' => 'La réservation doit être un identifiant valide.',
        ] + $this->messagesCommuns();
    }

    public function queryParameters(): array
    {
        return [
            'statut' => ['description' => 'en_attente, reussi, echoue ou rembourse.', 'example' => 'reussi', 'required' => false],
            'remboursementDu' => ['description' => 'true : paiements reçus (reussi ou echoue) marqués « à rembourser » ; false : tous les autres. Alias : remboursement_du.', 'example' => true, 'required' => false],
            'retardRemboursementMinutes' => ['description' => 'Remboursements dus depuis au moins N minutes (1 à 525600), y compris ceux dont les essais sont épuisés : point d\'arrivée des alertes `remboursements_en_retard` et `remboursements_en_echec`. Alias : retard_remboursement_minutes.', 'example' => 30, 'required' => false],
            'reservation' => ['description' => 'Identifiant de la réservation.', 'example' => '0198a1b2-0000-7000-8000-000000000020', 'required' => false],
            'page' => ['description' => 'Numéro de page (20 résultats par page).', 'example' => 1, 'required' => false],
        ];
    }

    /** @return array{statut: ?string, remboursementDu: ?bool, retardRemboursementMinutes: ?int, reservation: ?string} */
    public function filtres(): array
    {
        $v = $this->validated();

        return [
            'statut' => $v['statut'] ?? null,
            'remboursementDu' => $this->booleen('remboursementDu'),
            'retardRemboursementMinutes' => filled($v['retardRemboursementMinutes'] ?? null) ? (int) $v['retardRemboursementMinutes'] : null,
            'reservation' => $v['reservation'] ?? null,
        ];
    }
}
