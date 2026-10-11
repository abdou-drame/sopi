<?php

namespace App\Services;

use App\Models\Prestataire;
use App\Models\Prestation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Catalogue d'un prestataire (RF09) : création, modification et désactivation de ses prestations.
 * Le domaine est toujours le métier du prestataire (posé par le modèle Prestation) ; rien ne se supprime.
 */
class PrestationService
{
    public const MESSAGE_DOUBLON = 'Vous proposez déjà une prestation avec ce nom.';

    /** @return Collection<int, Prestation> Actives et inactives, les plus récentes d'abord. */
    public function lister(Prestataire $prestataire): Collection
    {
        return $prestataire->prestations()->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    /**
     * @param  array{nom: string, duree_minutes: int, prix: int|float|string}  $donnees
     *
     * @throws ValidationException si le prestataire propose déjà une prestation de ce nom
     */
    public function creer(Prestataire $prestataire, array $donnees): Prestation
    {
        return $this->enregistrer($prestataire, fn () => $prestataire->prestations()->create($donnees), $donnees['nom']);
    }

    /**
     * Modification partielle. Renvoie null si la prestation n'existe pas OU n'appartient pas à ce prestataire
     * (les deux cas sont indiscernables pour l'appelant).
     *
     * @param  array{nom?: string, duree_minutes?: int, prix?: int|float|string, actif?: bool}  $donnees
     *
     * @throws ValidationException si le nouveau nom est déjà pris par une autre prestation du prestataire
     */
    public function modifier(Prestataire $prestataire, string $prestationId, array $donnees): ?Prestation
    {
        $prestation = $prestataire->prestations()->whereKey($prestationId)->first();
        if ($prestation === null) {
            return null;
        }

        return $this->enregistrer(
            $prestataire,
            function () use ($prestation, $donnees) {
                $prestation->update($donnees);

                return $prestation;
            },
            $donnees['nom'] ?? null,
            $prestation->id,
        );
    }

    /** Vérifie l'unicité du nom (sans casse ni espaces superflus) puis exécute l'écriture, dans une transaction. */
    private function enregistrer(Prestataire $prestataire, callable $ecriture, ?string $nom, ?string $prestationId = null): Prestation
    {
        try {
            return DB::transaction(function () use ($prestataire, $ecriture, $nom, $prestationId) {
                if ($nom !== null) {
                    // Verrou sur le prestataire : deux créations simultanées du même nom sont sérialisées.
                    Prestataire::whereKey($prestataire->id)->lockForUpdate()->first();
                    $this->verifierNomLibre($prestataire, $nom, $prestationId);
                }

                return $ecriture();
            });
        } catch (UniqueConstraintViolationException) {
            // Filet de sécurité : la contrainte unique (prestataire_id, nom) de la base a tranché.
            throw $this->erreurDoublon();
        }
    }

    private function verifierNomLibre(Prestataire $prestataire, string $nom, ?string $sauf): void
    {
        $cle = $this->cle($nom);

        // Comparaison en PHP : lower() de SQLite ne gère pas les lettres accentuées.
        $existe = $prestataire->prestations()
            ->when($sauf !== null, fn ($q) => $q->whereKeyNot($sauf))
            ->pluck('nom')
            ->contains(fn (string $autre) => $this->cle($autre) === $cle);

        if ($existe) {
            throw $this->erreurDoublon();
        }
    }

    private function cle(string $nom): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nom)));
    }

    private function erreurDoublon(): ValidationException
    {
        return ValidationException::withMessages(['nom' => [self::MESSAGE_DOUBLON]]);
    }
}
