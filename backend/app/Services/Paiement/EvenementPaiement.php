<?php

namespace App\Services\Paiement;

use App\Enums\StatutPaiement;
use App\Enums\StatutRetrait;

/**
 * Événement reçu par webhook. Pour un paiement : référence, résultat (REUSSI ou ECHOUE) et montant en FCFA (« 3000.00 »).
 * Pour un versement : statutVersement (REUSSI ou ECHOUE), référence marchande et identifiant du retrait si le message les porte.
 */
final readonly class EvenementPaiement
{
    public function __construct(
        public string $reference,
        public StatutPaiement $statut,
        public string $montant,
        public GenreEvenement $genre = GenreEvenement::Paiement,
        public ?StatutRetrait $statutVersement = null,
        public ?string $referenceMarchande = null,
        public ?string $retraitId = null,
    ) {}

    /** Événement à acquitter sans rien faire. */
    public static function ignore(): self
    {
        return new self('', StatutPaiement::EnAttente, '0.00', GenreEvenement::Ignore);
    }
}
