<?php

namespace App\Http\Resources;

use App\Models\Litige;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Litige vu par l'une des trois parties. La partie adverse n'est jamais identifiée plus finement que « prénom + initiale
 * du nom » (client vu par le prestataire) ou « nom de l'entreprise » (prestataire vu par le client) : ni téléphone,
 * ni e-mail, ni identifiant. L'administrateur voit les coordonnées complètes et les paiements de la réservation.
 *
 * @mixin Litige
 */
class LitigeResource extends JsonResource
{
    public const VUE_CLIENT = 'client';

    public const VUE_PRESTATAIRE = 'prestataire';

    public const VUE_ADMIN = 'admin';

    public static $wrap = null;

    private string $vue = self::VUE_CLIENT;

    public static function pour(Litige $litige, string $vue): self
    {
        $ressource = new self($litige);
        $ressource->vue = $vue;

        return $ressource;
    }

    /** @param  iterable<Litige>  $litiges */
    public static function liste($litiges, string $vue)
    {
        $collection = self::collection($litiges);
        $collection->collection->each(fn (self $r) => $r->vue = $vue);

        return $collection;
    }

    public function toArray(Request $request): array
    {
        $reservation = $this->reservation;

        $donnees = [
            'id' => $this->id,
            'statut' => $this->statut->value,
            'description' => $this->description,
            'dateCreation' => $this->created_at->toIso8601String(),
            'reservation' => [
                'id' => $reservation->id,
                'statut' => $reservation->statut->value,
                'dateDebut' => $reservation->creneau->date_debut->toIso8601String(),
                'prestation' => $reservation->prestation->nom,
            ],
            'nombrePreuves' => $this->preuves_count ?? $this->whenLoaded('preuves', fn () => $this->preuves->count()),
            'reponsePrestataire' => $this->reponse_prestataire,
            'reponseLe' => $this->reponse_le?->toIso8601String(),
            'decision' => $this->decision?->value,
            'motifDecision' => $this->motif_decision,
            'clotureLe' => $this->cloture_le?->toIso8601String(),
        ];

        if ($this->relationLoaded('preuves')) {
            $donnees['preuves'] = $this->preuves->map(fn ($preuve) => [
                'id' => $preuve->id,
                'url' => route($this->vue.'.litiges.preuve', ['id' => $this->id, 'preuve' => $preuve->id], false),
            ])->all();
        }

        return $donnees + match ($this->vue) {
            self::VUE_CLIENT => ['prestataire' => ['nomEntreprise' => $this->prestataire->nom_entreprise]],
            self::VUE_PRESTATAIRE => ['client' => $this->clientAbrege($this->client)],
            default => $this->vueAdmin(),
        };
    }

    private function vueAdmin(): array
    {
        $paiements = $this->reservation->relationLoaded('paiements')
            ? $this->reservation->paiements->map(fn ($p) => [
                'id' => $p->id,
                'statut' => $p->statut->value,
                'montantAcompte' => (float) $p->montant_acompte,
                'commission' => (float) $p->commission,
                'payeLe' => $p->paye_le?->toIso8601String(),
                'rembourseLe' => $p->rembourse_le?->toIso8601String(),
            ])->all()
            : [];

        return [
            'client' => $this->coordonnees($this->client) + ['id' => $this->client->id],
            'prestataire' => $this->coordonnees($this->prestataire->user) + [
                'id' => $this->prestataire->user->id,
                'nomEntreprise' => $this->prestataire->nom_entreprise,
            ],
            'prixApplique' => (float) $this->reservation->prix_applique,
            'paiements' => $paiements,
            'decidePar' => $this->decidePar?->id,
            'sanctionId' => $this->relationLoaded('sanction') ? $this->sanction?->id : null,
        ];
    }

    /** @return array{prenom: string, initialeNom: string} */
    private function clientAbrege(User $client): array
    {
        return [
            'prenom' => $client->prenom,
            'initialeNom' => mb_strtoupper(mb_substr(trim($client->nom), 0, 1)),
        ];
    }

    /** @return array{nom: string, prenom: string, telephone: string, email: ?string} */
    private function coordonnees(User $utilisateur): array
    {
        return [
            'nom' => $utilisateur->nom,
            'prenom' => $utilisateur->prenom,
            'telephone' => $utilisateur->telephone,
            'email' => $utilisateur->email,
        ];
    }
}
