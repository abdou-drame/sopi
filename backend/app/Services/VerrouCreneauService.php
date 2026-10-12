<?php

namespace App\Services;

use App\Enums\StatutCompte;
use App\Enums\StatutCreneau;
use App\Models\Creneau;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Verrou temporaire des créneaux par les clients (RF03, RG08, RG09).
 * L'atomicité est portée par Creneau::verrouillerSiLibre(), une seule mise à jour conditionnelle.
 */
class VerrouCreneauService
{
    /**
     * Verrouille un créneau pour le client. Un client n'a qu'un verrou à la fois : le précédent est libéré
     * seulement après que le nouveau est obtenu (un refus ne lui fait donc rien perdre).
     * Idempotent : si le client tient déjà un verrou valide sur ce créneau, il est renvoyé tel quel, sans prolongation.
     *
     * @return Creneau|false|null le créneau verrouillé ; false si refusé (déjà pris) ; null si introuvable
     */
    public function verrouiller(User $client, string $id): Creneau|false|null
    {
        $creneau = $this->visible($id);
        if ($creneau === null) {
            return null;
        }

        if ($this->detientVerrouValide($creneau, $client)) {
            return $creneau;
        }

        return DB::transaction(function () use ($creneau, $client) {
            if (! $creneau->verrouillerSiLibre($this->dureeSecondes(), $client->id)) {
                return false;
            }

            Creneau::query()
                ->where('verrouille_par', $client->id)
                ->where('statut', StatutCreneau::Verrouille->value)
                ->whereKeyNot($creneau->id)
                ->update(['statut' => StatutCreneau::Libre->value, 'verrou_expire_a' => null, 'verrouille_par' => null]);

            return $creneau;
        });
    }

    /** Libère le verrou du client. Faux si ce créneau n'est pas verrouillé par lui (ou n'existe pas). */
    public function liberer(User $client, string $id): bool
    {
        if (! Str::isUuid($id)) {
            return false;
        }

        $creneau = Creneau::query()->find($id);

        return $creneau !== null && $creneau->libererVerrou($client->id);
    }

    /** Créneau à venir d'un prestataire dont le compte est actif, ou null. */
    private function visible(string $id): ?Creneau
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return Creneau::query()
            ->aVenir()
            ->whereHas('prestataire.user', fn (Builder $q) => $q->where('statut', StatutCompte::Actif->value))
            ->find($id);
    }

    private function detientVerrouValide(Creneau $creneau, User $client): bool
    {
        return $creneau->statut === StatutCreneau::Verrouille
            && $creneau->verrouille_par === $client->id
            && $creneau->verrou_expire_a !== null
            && $creneau->verrou_expire_a->gt(now());
    }

    private function dureeSecondes(): int
    {
        return max(1, (int) config('sopi.creneaux.verrou_minutes')) * 60;
    }
}
