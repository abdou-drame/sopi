<?php

namespace Database\Factories;

use App\Enums\DecisionLitige;
use App\Enums\StatutLitige;
use App\Models\Litige;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Par défaut : litige OUVERT sur une réservation CONFIRMEE, avec le client et le prestataire de cette réservation.
 *
 * @extends Factory<Litige>
 */
class LitigeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory()->confirmee(),
            'client_id' => fn (array $attributs) => Reservation::findOrFail($attributs['reservation_id'])->client_id,
            'prestataire_id' => fn (array $attributs) => Reservation::findOrFail($attributs['reservation_id'])->creneau->prestataire_id,
            'statut' => StatutLitige::Ouvert,
            'motif' => 'Prestataire absent',
            'description' => 'Le prestataire ne s\'est pas présenté au rendez-vous.',
        ];
    }

    public function repondu(?string $reponse = null): static
    {
        return $this->state(fn () => ['reponse_prestataire' => $reponse ?? 'J\'étais sur place à l\'heure convenue.', 'reponse_le' => now()]);
    }

    /** Litige clôturé par un administrateur (créé à la volée si besoin). */
    public function cloture(DecisionLitige $decision = DecisionLitige::Rejet, string $motif = 'Signalement non fondé.'): static
    {
        return $this->state(fn () => [
            'statut' => StatutLitige::Cloture,
            'decision' => $decision,
            'motif_decision' => $motif,
            'decide_par_id' => User::factory()->administrateur(),
            'cloture_le' => now(),
        ]);
    }
}
