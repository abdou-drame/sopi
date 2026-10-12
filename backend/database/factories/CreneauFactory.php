<?php

namespace Database\Factories;

use App\Enums\StatutCreneau;
use App\Models\Creneau;
use App\Models\Prestataire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Créneaux futurs d'une heure, sur des débuts distincts (la base interdit deux débuts identiques chez un prestataire).
 *
 * @extends Factory<Creneau>
 */
class CreneauFactory extends Factory
{
    /** Décalage en heures, incrémenté à chaque création pour ne jamais répéter un début. */
    private static int $decalage = 0;

    public function definition(): array
    {
        $debut = now()->startOfHour()->addHours(24 + self::$decalage++);

        return [
            'prestataire_id' => Prestataire::factory(),
            'date_debut' => $debut,
            'date_fin' => $debut->copy()->addMinutes(60),
            'statut' => StatutCreneau::Libre,
            'verrou_expire_a' => null,
        ];
    }

    public function libre(): static
    {
        return $this->state(['statut' => StatutCreneau::Libre, 'verrou_expire_a' => null]);
    }

    /** Verrou en cours : il expire dans 5 minutes par défaut (SLOT_LOCK_MINUTES). */
    public function verrouille(?\DateTimeInterface $expireA = null): static
    {
        return $this->state(fn () => [
            'statut' => StatutCreneau::Verrouille,
            'verrou_expire_a' => $expireA ?? now()->addMinutes(5),
        ]);
    }

    public function reserve(): static
    {
        return $this->state(['statut' => StatutCreneau::Reserve, 'verrou_expire_a' => null]);
    }

    public function indisponible(): static
    {
        return $this->state(['statut' => StatutCreneau::Indisponible, 'verrou_expire_a' => null]);
    }
}
