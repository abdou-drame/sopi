<?php

namespace App\Exceptions;

use RuntimeException;

/** Configuration des notifications inutilisable (pilote inconnu ou pas encore disponible). Le message est en français. */
class ConfigurationNotificationInvalide extends RuntimeException {}
