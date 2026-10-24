<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Avis tel que le voit le prestataire noté : mêmes champs que pour les clients, plus `peutSignaler` (RF20) qui indique
 * si le prestataire peut encore demander l'examen de l'avis (ni déjà signalé, ni déjà modéré).
 */
class AvisPrestataireResource extends AvisPublicResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'peutSignaler' => $this->signale_le === null && $this->modere_le === null,
        ];
    }
}
