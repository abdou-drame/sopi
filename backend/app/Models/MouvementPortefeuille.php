<?php

namespace App\Models;

use App\Enums\TypeMouvementPortefeuille;
use Database\Factories\MouvementPortefeuilleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne du journal du solde d'un prestataire. Écrite par les services du portefeuille, jamais en masse. */
class MouvementPortefeuille extends Model
{
    /** @use HasFactory<MouvementPortefeuilleFactory> */
    use HasFactory, HasUuids;

    protected $table = 'mouvements_portefeuille';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type' => TypeMouvementPortefeuille::class,
            'montant' => 'decimal:2',
            'solde_apres' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function retrait(): BelongsTo
    {
        return $this->belongsTo(Retrait::class);
    }

    /** Enregistre un mouvement ;à appeler dans la transaction qui modifie le solde, juste après la modification. */
    public static function enregistrer(
        string $prestataireId,
        TypeMouvementPortefeuille $type,
        string|int|float $montant,
        string $libelle,
        string|int|float $soldeApres,
        ?string $reservationId = null,
        ?string $retraitId = null,
    ): self {
        $mouvement = new self;
        $mouvement->prestataire_id = $prestataireId;
        $mouvement->type = $type;
        $mouvement->montant = $montant;
        $mouvement->libelle = $libelle;
        $mouvement->solde_apres = $soldeApres;
        $mouvement->reservation_id = $reservationId;
        $mouvement->retrait_id = $retraitId;
        $mouvement->save();

        return $mouvement;
    }
}
