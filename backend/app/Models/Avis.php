<?php

namespace App\Models;

use Database\Factories\AvisFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis d'un client sur une prestation terminée (diagramme de classes) : note de 1 à 5 et commentaire facultatif.
 * Créé uniquement par AvisService (aucune affectation de masse), non modifiable après dépôt. Peut être signalé par le
 * prestataire puis modéré par l'administrateur (signale_le, modere_le, modere_par_id, masque_le : voir la migration de modération).
 */
class Avis extends Model
{
    /** @use HasFactory<AvisFactory> */
    use HasFactory, HasUuids;

    // Le pluriel de « avis » est « avis » : on fixe le nom pour ne rien laisser au hasard.
    protected $table = 'avis';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'note' => 'integer',
            'signale_le' => 'datetime',
            'modere_le' => 'datetime',
            'masque_le' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }

    /** Administrateur qui a modéré l'avis (supprimé ou conservé), modereParId du diagramme de classes. */
    public function moderePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modere_par_id');
    }
}
