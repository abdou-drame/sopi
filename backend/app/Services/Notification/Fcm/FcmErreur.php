<?php

namespace App\Services\Notification\Fcm;

use RuntimeException;

/** Échec d'un appel à Google (authentification ou envoi FCM). Le message ne contient que le code HTTP et le code d'erreur Google. */
class FcmErreur extends RuntimeException {}
