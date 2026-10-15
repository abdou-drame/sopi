<?php

namespace App\Models;

use App\Enums\OperateurRetrait;
use App\Enums\StatutRetrait;
use Database\Factories\RetraitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retrait des gains d'un prestataire vers son compte mobile money (diagramme de classes). Le statut et les montants
 * changent par des méthodes métier de service (M7.4) : aucune affectation de masse.
 */
class Retrait extends Model
{
    /** @use HasFactory<RetraitFactory> */
    use HasFactory, HasUuids;

    protected $table = 'retraits';

    protected $fillable = [];

    protected $attributes = [
        'statut' => 'en_cours',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutRetrait::class,
            'operateur' => OperateurRetrait::class,
            'montant' => 'decimal:2',
            'traite_le' => 'datetime',
            'recredite_le' => 'datetime',
        ];
    }

    /** Crée le retrait EN_COURS d'un prestataire (jamais en masse). Le débit du solde est fait par l'appelant, dans la même transaction. */
    public static function ouvrir(string $prestataireId, int $montant, string $telephoneVersement, ?OperateurRetrait $operateur = null): self
    {
        $retrait = new self;
        $retrait->prestataire_id = $prestataireId;
        $retrait->montant = $montant;
        $retrait->telephone_versement = $telephoneVersement;
        $retrait->operateur = $operateur;
        $retrait->save();

        return $retrait;
    }

    /** EN_COURS vers REUSSI avec la référence du versement, une seule fois. Vrai si exactement une ligne a changé. */
    public function reussirSiEnCours(?string $reference): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutRetrait::EnCours->value)
            ->update(['statut' => StatutRetrait::Reussi->value, 'reference_dexpay' => $reference, 'traite_le' => now(), 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /** Mémorise la référence du versement chez le prestataire de paiement tant que le retrait est EN_COURS (versement accepté, pas encore terminé). */
    public function enregistrerReferenceSiEnCours(string $reference): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutRetrait::EnCours->value)
            ->update(['reference_dexpay' => $reference, 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /** EN_COURS vers ECHOUE, une seule fois. Vrai si exactement une ligne a changé. */
    public function echouerSiEnCours(): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutRetrait::EnCours->value)
            ->update(['statut' => StatutRetrait::Echoue->value, 'traite_le' => now(), 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    /**
     * Pose la garde du recrédit (recredite_le), une seule fois : seulement pour un retrait ECHOUE pas encore recrédité.
     * Vrai si exactement une ligne a changé (l'appelant recrédite alors le solde, dans la même transaction).
     */
    public function recrediterSiPossible(): bool
    {
        $modifies = static::query()
            ->whereKey($this->getKey())
            ->where('statut', StatutRetrait::Echoue->value)
            ->whereNull('recredite_le')
            ->update(['recredite_le' => now(), 'updated_at' => now()]);

        return $modifies === 1 && $this->refresh() !== null;
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }
}
