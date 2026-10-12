<?php

namespace App\Exceptions;

use RuntimeException;

/** Configuration du paiement inutilisable (pilote inconnu ou indisponible, taux incohérents). Le message est en français. */
class ConfigurationPaiementInvalide extends RuntimeException {}
