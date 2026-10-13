<?php

namespace App\Models;

use Database\Factories\LitigePreuveFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Preuve photographique d'un litige : nom du fichier sur le disque privé, jamais un chemin. */
class LitigePreuve extends Model
{
    /** @use HasFactory<LitigePreuveFactory> */
    use HasFactory, HasUuids;

    protected $table = 'litige_preuves';

    protected $fillable = [];

    public function litige(): BelongsTo
    {
        return $this->belongsTo(Litige::class);
    }
}
