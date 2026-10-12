<?php

namespace Database\Factories;

use App\Enums\StatutReservation;
use App\Models\Creneau;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Par défaut : EN_ATTENTE, avec un créneau et une prestation du même prestataire.
 *
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => User::factory(),
            'creneau_id' => Creneau::factory(),
            // Même prestataire que le créneau.
            'prestation_id' => fn (array $attributs) => Prestation::factory()->for(Creneau::findOrFail($attributs['creneau_id'])->prestataire),
            'statut' => StatutReservation::EnAttente,
            'motif_refus' => null,
            'code_reservation' => null,
            'acceptee_le' => null,
        ];
    }

    public function enAttente(): static
    {
        return $this->state(['statut' => StatutReservation::EnAttente]);
    }

    /** En attente de paiement : acceptee_le est renseigné (départ du délai de 2 h). */
    public function acceptee(): static
    {
        return $this->state(fn () => ['statut' => StatutReservation::Acceptee, 'acceptee_le' => now()]);
    }

    public function confirmee(): static
    {
        return $this->avecCode(StatutReservation::Confirmee);
    }

    public function enCours(): static
    {
        return $this->avecCode(StatutReservation::EnCours);
    }

    public function terminee(): static
    {
        return $this->avecCode(StatutReservation::Terminee);
    }

    public function refusee(?string $motif = null): static
    {
        return $this->state(fn () => ['statut' => StatutReservation::Refusee, 'motif_refus' => $motif ?? 'Je ne suis pas disponible à cette heure.']);
    }

    public function annulee(): static
    {
        return $this->state(['statut' => StatutReservation::Annulee]);
    }

    private function avecCode(StatutReservation $statut): static
    {
        return $this->state(fn () => [
            'statut' => $statut,
            'acceptee_le' => now(),
            'code_reservation' => strtoupper(Str::random(6)),
        ]);
    }
}
