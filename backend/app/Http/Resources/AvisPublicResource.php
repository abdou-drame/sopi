<?php

namespace App\Http\Resources;

use App\Models\Avis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Avis tel que le voient les clients et le prestataire noté. L'auteur n'est exposé que par son prénom et l'initiale de son nom
 * (« Awa D. ») : ni nom complet, ni téléphone, ni e-mail, ni identifiant. Le client doit être chargé.
 *
 * @mixin Avis
 */
class AvisPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'commentaire' => $this->commentaire,
            'auteur' => $this->auteur(),
            'dateCreation' => $this->created_at->toIso8601String(),
        ];
    }

    private function auteur(): string
    {
        $prenom = trim((string) $this->client?->prenom);
        $initiale = mb_strtoupper(mb_substr(trim((string) $this->client?->nom), 0, 1));

        return trim($prenom.($initiale === '' ? '' : ' '.$initiale.'.')) ?: 'Client';
    }
}
