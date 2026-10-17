<?php

namespace App\Http\Resources;

use App\Models\Prestataire;
use App\Services\ConsultationAvisService;
use App\Services\Paiement\CalculPaiement;
use App\Services\PhotoAtelierService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Profil public d'un prestataire (vue client). Aucune donnée personnelle, aucun chemin de stockage. noteMoyenne (null sans avis)
 * et nombreAvis viennent des colonnes calculées par le service, qui charge aussi les prestations (actives seulement).
 *
 * @mixin Prestataire
 */
class ProfilPublicPrestataireResource extends JsonResource
{
    public static $wrap = null;

    /** Identité d'un prestataire, commune au profil public et au profil du prestataire connecté (GET /me). */
    public static function identite(Prestataire $prestataire): array
    {
        return [
            'id' => $prestataire->id,
            'nomEntreprise' => $prestataire->nom_entreprise,
            'metier' => $prestataire->metier,
            'quartier' => $prestataire->quartier,
        ];
    }

    public function toArray(Request $request): array
    {
        $photos = app(PhotoAtelierService::class);
        $calcul = CalculPaiement::depuisConfiguration();
        $pourcentage = round((float) config('sopi.paiement.acompte_taux') * 100, 2);

        return [
            ...self::identite($this->resource),
            'adresse' => $this->adresse,
            ...ConsultationAvisService::formater($this->note_moyenne, $this->nombre_avis),
            'acomptePourcentage' => $pourcentage == (int) $pourcentage ? (int) $pourcentage : $pourcentage,
            'prestations' => $this->prestations->map(fn ($prestation) => [
                'id' => $prestation->id,
                'nom' => $prestation->nom,
                'domaine' => $prestation->domaine,
                'dureeMinutes' => $prestation->duree_minutes,
                'prix' => (float) $prestation->prix,
                'montantAcompte' => (float) $calcul->calculer((string) $prestation->prix)->montantAcompte,
            ])->values()->all(),
            'photos' => collect($this->photos_atelier ?? [])->map(function (string $nom) use ($photos) {
                $id = $photos->identifiant($nom);

                // Chemin relatif de l'API : le client le complète avec l'URL de base.
                return ['id' => $id, 'url' => route('prestataires.photo', ['id' => $this->id, 'photo' => $id], false)];
            })->values()->all(),
        ];
    }
}
