<?php

namespace App\Http\Resources;

use App\Models\Prestataire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prestataire dans les résultats de recherche (vue client). Aucune donnée personnelle : ni nom, ni prénom, ni téléphone,
 * ni e-mail, ni identifiant utilisateur. Pas de note tant que les avis n'existent pas. L'utilisateur n'est jamais chargé.
 *
 * @mixin Prestataire
 */
class PrestataireRechercheResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nomEntreprise' => $this->nom_entreprise,
            'metier' => $this->metier->value,
            'quartier' => $this->quartier,
            'adresse' => $this->adresse,
            'prixMin' => (float) $this->prix_min,
            'nombrePrestations' => (int) $this->nombre_prestations,
        ];
    }
}
