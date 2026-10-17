<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Quartier du référentiel proposé à l'inscription d'un prestataire. Simple liste de suggestions : le quartier d'un
 * prestataire (prestataires.quartier) reste un texte libre et n'est jamais validé contre cette table.
 */
class Quartier extends Model
{
    /** @use HasFactory<QuartierFactory> */
    use HasFactory, HasUuids;

    protected $table = 'quartiers';

    /** Quartiers de départ (la plateforme ne couvre d'abord que Keur Massar). La migration les insère aussi. */
    public const NOMS_DE_DEPART = ['Keur Massar Nord', 'Keur Massar Sud'];

    protected $fillable = ['nom'];
}
