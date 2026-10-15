<?php

namespace App\Services\Paiement;

use App\Enums\OperateurRetrait;

/** Informations d'un retrait nécessaires au versement : identifiant (référence déterministe), opérateur et bénéficiaire. */
final readonly class ContexteVersement
{
    public function __construct(
        public string $retraitId,
        public OperateurRetrait $operateur,
        public ?string $nomBeneficiaire = null,
    ) {}

    /** Référence marchande du versement : déterministe, 40 caractères. */
    public static function referenceDe(string $retraitId): string
    {
        return 'RTR-'.$retraitId;
    }

    /** Retrouve l'identifiant du retrait dans une référence marchande ; null si elle n'a pas la forme attendue. */
    public static function retraitIdDe(?string $reference): ?string
    {
        if ($reference !== null && preg_match('/^RTR-([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i', $reference, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }
}
