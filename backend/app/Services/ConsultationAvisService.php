<?php

namespace App\Services;

use App\Models\Avis;
use App\Models\Prestataire;
use Illuminate\Pagination\LengthAwarePaginator;

/** Consultation des avis reçus par un prestataire (RF05) : liste paginée et note moyenne. */
class ConsultationAvisService
{
    public const PAR_PAGE = 20;

    /** Avis du prestataire non masqués par l'administrateur, du plus récent au plus ancien ; les auteurs sont chargés en une requête. */
    public function lister(Prestataire $prestataire): LengthAwarePaginator
    {
        return Avis::query()
            ->visibles()
            ->where('prestataire_id', $prestataire->id)
            ->with('client:id,nom,prenom')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE);
    }

    /**
     * Note moyenne (arrondie à 1 décimale, null sans avis) et nombre d'avis, en UNE requête d'agrégat.
     *
     * @return array{noteMoyenne: ?float, nombreAvis: int}
     */
    public function synthese(Prestataire $prestataire): array
    {
        $ligne = Avis::query()->visibles()->where('prestataire_id', $prestataire->id)->selectRaw('avg(note) as moyenne, count(*) as nombre')->toBase()->first();

        return self::formater($ligne->moyenne, $ligne->nombre);
    }

    /** Arrondi et typage communs à toutes les réponses (agrégat sur un prestataire, ou colonnes calculées d'une liste). */
    public static function formater(mixed $moyenne, mixed $nombre): array
    {
        return [
            'noteMoyenne' => $moyenne === null ? null : round((float) $moyenne, 1),
            'nombreAvis' => (int) $nombre,
        ];
    }
}
