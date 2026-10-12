<?php

namespace App\Models;

use App\Enums\StatutReservation;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réservation d'un créneau par un client pour une prestation (diagramme de classes).
 * Au plus une réservation active par créneau (index unique partiel). Les transitions de statut viennent en M5.2+.
 */
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory, HasUuids;

    protected $table = 'reservations';

    // Clés étrangères, statut, motif, code et acceptee_le sont posés par le métier, jamais en masse.
    protected $fillable = [];

    protected $attributes = [
        'statut' => 'en_attente',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutReservation::class,
            'acceptee_le' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creneau(): BelongsTo
    {
        return $this->belongsTo(Creneau::class);
    }

    public function prestation(): BelongsTo
    {
        return $this->belongsTo(Prestation::class);
    }
}
