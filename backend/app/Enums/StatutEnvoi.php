<?php

namespace App\Enums;

/** État d'envoi d'une notification sur un canal externe (e-mail ou push). */
enum StatutEnvoi: string
{
    /** Le canal ne s'applique pas : non demandé, adresse ou jeton absent, compte bloqué, ou destinataire administrateur. */
    case NonApplicable = 'non_applicable';
    case EnAttente = 'en_attente';
    case Envoye = 'envoye';
    /** Le nombre maximal d'essais est atteint. */
    case Echoue = 'echoue';
}
