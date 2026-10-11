<?php

namespace App\Models;

use App\Enums\Metier;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Service générique rattaché à un métier (ex. Vidange, Coupe homme, Nettoyage à sec).
 * Chaque prestataire le propose sous forme de Prestation, avec son tarif et sa durée.
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasUuids;

    protected $table = 'services';

    protected $fillable = [
        'metier',
        'nom',
    ];

    protected function casts(): array
    {
        return [
            'metier' => Metier::class,
        ];
    }

    public function prestations(): HasMany
    {
        return $this->hasMany(Prestation::class);
    }
}
