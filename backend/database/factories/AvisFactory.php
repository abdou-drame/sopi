<?php

namespace Database\Factories;

use App\Models\Avis;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Par défaut : avis de 4 sur une réservation TERMINEE, avec le client et le prestataire de cette réservation.
 *
 * @extends Factory<Avis>
 */
class AvisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory()->terminee(),
            'client_id' => fn (array $attributs) => Reservation::findOrFail($attributs['reservation_id'])->client_id,
            'prestataire_id' => fn (array $attributs) => Reservation::findOrFail($attributs['reservation_id'])->creneau->prestataire_id,
            'note' => 4,
            'commentaire' => null,
        ];
    }

    public function note(int $note): static
    {
        return $this->state(['note' => $note]);
    }

    public function commente(?string $commentaire = null): static
    {
        return $this->state(['commentaire' => $commentaire ?? 'Prestation soignée, merci.']);
    }
}
