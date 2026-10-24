<?php

namespace App\Exceptions;

use RuntimeException;

/** Un document désigné dans le refus n'appartient pas au dossier décidé. */
class JustificatifHorsDossier extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Un des documents refusés n\'appartient pas à ce dossier.');
    }
}
