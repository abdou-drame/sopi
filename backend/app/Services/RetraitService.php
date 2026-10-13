<?php

namespace App\Services;

use App\Models\Prestataire;
use App\Models\Retrait;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/** Solde et retraits d'un prestataire (RF23, RG28 à RG33) : consultation (M7.3), demande de retrait (M7.4). */
class RetraitService
{
    public const PAR_PAGE = 20;

    /** Retraits du prestataire, du plus récent au plus ancien. */
    public function lister(Prestataire $prestataire): LengthAwarePaginator
    {
        return $prestataire->retraits()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE);
    }

    /** Détail d'un retrait, ou null s'il n'existe pas OU n'appartient pas à ce prestataire (même réponse, aucune fuite). */
    public function trouver(Prestataire $prestataire, string $id): ?Retrait
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return $prestataire->retraits()->find($id);
    }
}
