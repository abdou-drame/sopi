<?php

namespace App\Http\Requests\Admin;

/** Masquer un avis : le motif est obligatoire. */
class MasquerAvisRequest extends ModererAvisRequest
{
    protected function presenceDuMotif(): string
    {
        return 'required';
    }
}
