<?php

namespace App\Enums;

/** Pourquoi une réservation a été annulée automatiquement par les tâches planifiées. */
enum CauseExpiration: string
{
    /** Le prestataire n'a pas répondu dans le délai (RG10). */
    case SansReponse = 'sans_reponse';

    /** Le client n'a pas payé dans le délai après l'acceptation (RG11). */
    case Impayee = 'impayee';
}
