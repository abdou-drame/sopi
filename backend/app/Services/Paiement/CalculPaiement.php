<?php

namespace App\Services\Paiement;

use App\Exceptions\ConfigurationPaiementInvalide;
use InvalidArgumentException;

/**
 * Calcule l'acompte et la commission à partir du prix de la prestation, en arithmétique entière exacte (aucun flottant
 * dans les montants). Acompte = prix x taux d'acompte, commission = prix x taux de commission, arrondis au FCFA entier
 * (demi vers le haut). L'acompte vaut au moins 1 FCFA et la commission ne dépasse jamais l'acompte.
 * Les taux sont lus avec 6 décimales au plus.
 */
class CalculPaiement
{
    private const ECHELLE_TAUX = 1_000_000;

    private readonly int $acompteMicro;

    private readonly int $commissionMicro;

    /** @throws ConfigurationPaiementInvalide si les taux ne respectent pas 0 < commission <= acompte <= 1 */
    public function __construct(float $acompteTaux, float $commissionTaux)
    {
        $this->acompteMicro = (int) round($acompteTaux * self::ECHELLE_TAUX);
        $this->commissionMicro = (int) round($commissionTaux * self::ECHELLE_TAUX);

        if ($this->commissionMicro <= 0 || $this->commissionMicro > $this->acompteMicro || $this->acompteMicro > self::ECHELLE_TAUX) {
            throw new ConfigurationPaiementInvalide('Les taux de paiement sont invalides : il faut 0 < commission <= acompte <= 1.');
        }
    }

    public static function depuisConfiguration(): self
    {
        return new self((float) config('sopi.paiement.acompte_taux'), (float) config('sopi.paiement.commission_taux'));
    }

    /**
     * @param  string  $prix  prix en FCFA, décimal positif à 2 décimales au plus (« 10000.00 », « 12345 »)
     *
     * @throws InvalidArgumentException si le prix n'est pas un décimal strictement positif tenant dans decimal(12,2)
     */
    public function calculer(string $prix): MontantsPaiement
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $prix, $m) !== 1) {
            throw new InvalidArgumentException('Le prix doit être un montant positif avec au plus 2 décimales.');
        }

        $centimes = ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '', 2, '0');
        if ($centimes <= 0) {
            throw new InvalidArgumentException('Le prix doit être strictement positif.');
        }

        $acompte = max(1, $this->arrondi($centimes, $this->acompteMicro));
        $commission = min($acompte, $this->arrondi($centimes, $this->commissionMicro));

        return new MontantsPaiement($acompte.'.00', $commission.'.00');
    }

    /**
     * Différence exacte de deux montants décimaux (« 15000.00 » - « 4500.00 » = « 10500.00 »), jamais négative : reste à payer sur place.
     *
     * @throws InvalidArgumentException si un montant n'est pas un décimal positif à 2 décimales au plus
     */
    public static function soustraire(string $a, string $b): string
    {
        $centimes = max(0, self::enCentimes($a) - self::enCentimes($b));

        return intdiv($centimes, 100).'.'.str_pad((string) ($centimes % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function enCentimes(string $montant): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $montant, $m) !== 1) {
            throw new InvalidArgumentException('Le montant doit être positif avec au plus 2 décimales.');
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    /** prix (en centimes) x taux (en millionièmes) en FCFA entiers, demi vers le haut. Au plus 10^12 x 10^6 : tient dans un entier 64 bits. */
    private function arrondi(int $centimes, int $tauxMicro): int
    {
        $diviseur = 100 * self::ECHELLE_TAUX;

        return intdiv($centimes * $tauxMicro + intdiv($diviseur, 2), $diviseur);
    }
}
