<?php

namespace Database\Factories;

use App\Enums\StatutEnvoi;
use App\Enums\TypeNotification;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Par défaut : notification lue nulle, envoyée par les deux canaux.
 *
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => TypeNotification::ReservationConfirmee,
            'titre' => 'Réservation confirmée',
            'corps' => 'Votre réservation est confirmée.',
            'donnees' => null,
            'lue_le' => null,
            'canal_email_statut' => StatutEnvoi::Envoye,
            'canal_push_statut' => StatutEnvoi::Envoye,
            'tentatives' => 0,
            'prochain_essai_le' => null,
            'dernier_echec_classe' => null,
        ];
    }

    public function enAttente(): static
    {
        return $this->state(['canal_email_statut' => StatutEnvoi::EnAttente, 'canal_push_statut' => StatutEnvoi::EnAttente]);
    }

    public function lue(): static
    {
        return $this->state(['lue_le' => now()]);
    }
}
