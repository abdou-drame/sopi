<?php

namespace App\Http\Resources;

use App\Models\Prestataire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réponse de l'inscription d'un prestataire. Ne contient jamais le chemin des fichiers ni le hachage du jeton ;
 * le jeton de suivi en clair n'apparaît qu'ici, à l'inscription.
 *
 * @mixin Prestataire
 */
class InscriptionPrestataireResource extends JsonResource
{
    public static $wrap = null;

    public function __construct($resource, private readonly string $jetonSuivi)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->user->nom,
            'prenom' => $this->user->prenom,
            'nomEntreprise' => $this->nom_entreprise,
            'metier' => $this->metier,
            'statutCompte' => $this->user->statut->value,
            'jeton_suivi' => $this->jetonSuivi,
            'lien_suivi' => rtrim(config('app.url'), '/').'/api/v1/suivi/'.$this->jetonSuivi,
            'justificatifs' => $this->justificatifs->map(fn ($justificatif) => [
                'type' => $justificatif->type_document->value,
                'statut' => $justificatif->statut->value,
            ])->values(),
        ];
    }
}
