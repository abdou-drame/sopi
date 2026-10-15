<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Configuration du paiement inutilisable (pilote inconnu ou indisponible, taux incohérents). Le message est en français.
 * $variablesManquantes : NOMS des variables d'environnement absentes (jamais leurs valeurs), pour le journal.
 */
class ConfigurationPaiementInvalide extends RuntimeException
{
    /** @param list<string> $variablesManquantes */
    public function __construct(string $message = '', public readonly array $variablesManquantes = [])
    {
        parent::__construct($message);
    }
}
