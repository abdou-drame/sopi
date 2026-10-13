<?php

namespace App\Services;

use App\Enums\StatutCompte;
use App\Models\Prestataire;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Profil public d'un prestataire, vu par un client (RF02, RF05). Seuls les comptes actifs sont visibles. */
class ProfilPublicService
{
    public function __construct(private readonly PhotoAtelierService $photos) {}

    /** Prestataire visible avec ses prestations actives, ou null (inconnu, identifiant invalide ou compte non actif). */
    public function trouver(string $id): ?Prestataire
    {
        return $this->visible($id)
            ?->loadAvg('avis as note_moyenne', 'note')
            ->loadCount('avis as nombre_avis')
            ->load(['prestations' => fn ($q) => $q->where('actif', true)->orderBy('nom')->orderBy('id')]);
    }

    public function photo(string $id, string $photo): ?StreamedResponse
    {
        $prestataire = $this->visible($id);

        return $prestataire ? $this->photos->flux($prestataire, $photo) : null;
    }

    /** Prestataire dont le compte est actif, ou null (inconnu, identifiant invalide ou compte non actif). */
    public function visible(string $id): ?Prestataire
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return Prestataire::query()
            ->whereHas('user', fn (Builder $q) => $q->where('statut', StatutCompte::Actif->value))
            ->find($id);
    }
}
