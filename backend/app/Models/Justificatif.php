<?php

namespace App\Models;

use App\Enums\StatutJustificatif;
use App\Enums\TypeDocument;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pièce justificative d'un prestataire, vérifiée par un administrateur.
 */
class Justificatif extends Model
{
    /** @use HasFactory<\Database\Factories\JustificatifFactory> */
    use HasFactory, HasUuids;

    protected $table = 'justificatifs';

    // Le statut, le motif et l'administrateur vérificateur ne sont modifiés que par le circuit de validation.
    protected $fillable = [
        'type_document',
        'chemin_fichier',
        'date_soumission',
    ];

    protected $attributes = [
        'statut' => 'en_attente',
    ];

    // Le chemin du fichier (document d'identité, etc.) ne sort jamais dans une réponse JSON par défaut.
    protected $hidden = [
        'chemin_fichier',
    ];

    protected function casts(): array
    {
        return [
            'type_document' => TypeDocument::class,
            'statut' => StatutJustificatif::class,
            'date_soumission' => 'datetime',
        ];
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }

    /** Administrateur qui a vérifié le justificatif. */
    public function verificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifie_par');
    }
}
