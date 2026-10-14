<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compte client ou prestataire vu par l'administrateur (supervision : nom, e-mail et téléphone sont visibles). Jamais de mot de passe,
 * de jeton, de jeton push ni de lien de suivi. Le profil prestataire doit être chargé. En liste, une ligne ; la fiche y ajoute la
 * synthèse calculée par UtilisateursAdminService (voir {@see fiche()}).
 *
 * @mixin User
 */
class UtilisateurAdminResource extends JsonResource
{
    public static $wrap = null;

    /** @var array<string, mixed>|null */
    private ?array $synthese = null;

    /** @param  array<string, mixed>  $synthese */
    public static function fiche(User $utilisateur, array $synthese): self
    {
        $ressource = new self($utilisateur);
        $ressource->synthese = $synthese;

        return $ressource;
    }

    public function toArray(Request $request): array
    {
        $prestataire = $this->prestataire;

        $ligne = [
            'id' => $this->id,
            'role' => $this->role->value,
            'prenom' => $this->prenom,
            'nom' => $this->nom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'statut' => $this->statut->value,
            'dateCreation' => $this->created_at->toIso8601String(),
            'prestataire' => $prestataire === null ? null : [
                'id' => $prestataire->id,
                'metier' => $prestataire->metier->value,
                'quartier' => $prestataire->quartier,
                'nomEntreprise' => $prestataire->nom_entreprise,
            ],
        ];

        if ($this->synthese === null) {
            return $ligne;
        }

        $synthese = $this->synthese;
        // La fiche enrichit l'objet prestataire de la ligne au lieu de le remplacer.
        if (isset($synthese['prestataire']) && $ligne['prestataire'] !== null) {
            $synthese['prestataire'] = $ligne['prestataire'] + $synthese['prestataire'];
        }

        return array_merge($ligne, $synthese) + ['client' => null];
    }
}
