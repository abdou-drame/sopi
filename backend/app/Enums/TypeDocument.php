<?php

namespace App\Enums;

/** Types de pièces justificatives qu'un prestataire peut fournir : CNI, DIPLOME, ATTESTATION. */
enum TypeDocument: string
{
    case Cni = 'cni';
    case Diplome = 'diplome';
    case Attestation = 'attestation';
}
