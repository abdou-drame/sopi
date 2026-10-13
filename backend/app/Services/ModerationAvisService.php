<?php

namespace App\Services;

use App\Events\AvisModere;
use App\Exceptions\ReservationImpossibleException;
use App\Models\Avis;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signalement d'un avis par le prestataire noté, puis modération par l'administrateur (RF20) : supprimer (= masquer, la ligne est
 * conservée) ou conserver. Toutes les écritures sont des mises à jour conditionnelles : deux requêtes simultanées, une seule passe.
 */
class ModerationAvisService
{
    public const PAR_PAGE = 20;

    /**
     * Le prestataire signale un avis qu'il a reçu et qui est encore affiché. Un seul signalement par avis.
     *
     * @throws ReservationImpossibleException 404 (inconnu, d'un autre prestataire ou masqué), 409 (déjà signalé ou déjà modéré)
     */
    public function signaler(Prestataire $prestataire, string $id, string $motif): Avis
    {
        $avis = Str::isUuid($id)
            ? Avis::query()->visibles()->where('prestataire_id', $prestataire->id)->find($id)
            : null;

        if ($avis === null) {
            throw new ReservationImpossibleException('Avis introuvable.', Response::HTTP_NOT_FOUND);
        }

        $modifies = Avis::query()
            ->whereKey($avis->id)
            ->whereNull('signale_le')
            ->whereNull('modere_le')
            ->update(['signale_le' => now(), 'motif_signalement' => $motif]);

        if ($modifies === 0) {
            $moderee = $avis->fresh()->modere_le !== null;

            throw new ReservationImpossibleException($moderee ? 'Cet avis a déjà été modéré.' : 'Cet avis a déjà été signalé.', Response::HTTP_CONFLICT);
        }

        return $avis->refresh();
    }

    /**
     * Liste pour l'administrateur, du plus récent au plus ancien. Filtres : prestataire, note, masqué ou non, signalé ou non,
     * à traiter (signalé et pas encore modéré). Auteur et prestataire chargés en une requête chacun.
     *
     * @param  array{prestataire?: ?string, note?: ?int, masque?: ?bool, signale?: ?bool, atraiter?: ?bool}  $filtres
     */
    public function lister(array $filtres): LengthAwarePaginator
    {
        $requete = Avis::query()->with(['client:id,nom,prenom', 'prestataire:id,nom_entreprise']);

        if (! empty($filtres['prestataire'])) {
            $requete->where('prestataire_id', $filtres['prestataire']);
        }
        if (isset($filtres['note'])) {
            $requete->where('note', $filtres['note']);
        }
        if (isset($filtres['masque'])) {
            $filtres['masque'] ? $requete->whereNotNull('masque_le') : $requete->whereNull('masque_le');
        }
        if (isset($filtres['signale'])) {
            $filtres['signale'] ? $requete->whereNotNull('signale_le') : $requete->whereNull('signale_le');
        }
        if (! empty($filtres['atraiter'])) {
            $requete->whereNotNull('signale_le')->whereNull('modere_le');
        }

        return $requete->orderByDesc('created_at')->orderByDesc('id')->paginate(self::PAR_PAGE);
    }

    /**
     * Masque l'avis (supprimer) : il disparaît de la note moyenne, des listes publiques et de la recherche. Motif obligatoire.
     *
     * @throws ReservationImpossibleException 404 (inconnu), 409 (déjà masqué)
     */
    public function masquer(User $admin, string $id, string $motif): Avis
    {
        $avis = $this->trouver($id);

        $modifies = Avis::query()
            ->whereKey($avis->id)
            ->whereNull('masque_le')
            ->update(['masque_le' => now(), 'modere_le' => now(), 'modere_par_id' => $admin->id, 'motif_moderation' => $motif]);

        if ($modifies === 0) {
            throw new ReservationImpossibleException('Cet avis est déjà masqué.', Response::HTTP_CONFLICT);
        }

        AvisModere::dispatch($avis->refresh(), true);

        return $this->termine($avis);
    }

    /**
     * Garde l'avis affiché (conserver) après examen d'un signalement. Le motif est facultatif. Une seule décision de conservation.
     *
     * @throws ReservationImpossibleException 404 (inconnu), 409 (déjà modéré)
     */
    public function conserver(User $admin, string $id, ?string $motif): Avis
    {
        $avis = $this->trouver($id);

        $modifies = Avis::query()
            ->whereKey($avis->id)
            ->whereNull('modere_le')
            ->update(['modere_le' => now(), 'modere_par_id' => $admin->id, 'motif_moderation' => $motif]);

        if ($modifies === 0) {
            throw new ReservationImpossibleException('Cet avis a déjà été modéré.', Response::HTTP_CONFLICT);
        }

        AvisModere::dispatch($avis->refresh(), false);

        return $this->termine($avis);
    }

    /**
     * Rétablit un avis masqué : il est de nouveau affiché et compte dans la note. La trace de la modération (qui, quand, motif) est gardée.
     *
     * @throws ReservationImpossibleException 404 (inconnu), 409 (pas masqué)
     */
    public function retablir(User $admin, string $id): Avis
    {
        $avis = $this->trouver($id);

        $modifies = Avis::query()
            ->whereKey($avis->id)
            ->whereNotNull('masque_le')
            ->update(['masque_le' => null, 'modere_par_id' => $admin->id]);

        if ($modifies === 0) {
            throw new ReservationImpossibleException('Cet avis n\'est pas masqué.', Response::HTTP_CONFLICT);
        }

        return $this->termine($avis);
    }

    /** @throws ReservationImpossibleException 404 */
    private function trouver(string $id): Avis
    {
        $avis = Str::isUuid($id) ? Avis::query()->find($id) : null;

        return $avis ?? throw new ReservationImpossibleException('Avis introuvable.', Response::HTTP_NOT_FOUND);
    }

    private function termine(Avis $avis): Avis
    {
        $avis->refresh()->load(['client:id,nom,prenom', 'prestataire:id,nom_entreprise']);

        return $avis;
    }
}
