<?php

namespace Database\Factories;

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Par défaut : EN_ATTENTE, pour un tarif fictif de 10 000 FCFA (acompte 30 % = 3 000, commission 10 % = 1 000).
 *
 * @extends Factory<Paiement>
 */
class PaiementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory()->acceptee(),
            'montant_acompte' => '3000.00',
            'commission' => '1000.00',
            'statut' => StatutPaiement::EnAttente,
            'reference_dexpay' => null,
            'paye_le' => null,
            'rembourse_le' => null,
        ];
    }

    public function enAttente(): static
    {
        return $this->state(['statut' => StatutPaiement::EnAttente]);
    }

    public function reussi(): static
    {
        return $this->state(fn () => ['statut' => StatutPaiement::Reussi, 'reference_dexpay' => $this->reference(), 'paye_le' => now()]);
    }

    public function echoue(): static
    {
        return $this->state(fn () => ['statut' => StatutPaiement::Echoue, 'reference_dexpay' => $this->reference()]);
    }

    public function rembourse(): static
    {
        return $this->state(fn () => [
            'statut' => StatutPaiement::Rembourse,
            'reference_dexpay' => $this->reference(),
            'paye_le' => now()->subHour(),
            'rembourse_le' => now(),
        ]);
    }

    private function reference(): string
    {
        return 'DXP-'.Str::upper(Str::random(12));
    }
}
