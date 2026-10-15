<?php

namespace App\Services;

use App\Exceptions\ReservationImpossibleException;
use App\Models\Metier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gestion des métiers de la plateforme par l'administrateur. Un métier n'est jamais supprimé : il est désactivé (plus de nouvelle
 * inscription), ses prestataires existants le gardent. Le code, déduit du libellé à la création, ne change plus.
 */
class MetierService
{
    public const LIBELLE_MAX = 60;

    public const CODE_MAX = 20;

    /** Code stocké chez les prestataires : libellé en minuscules, sans accent ni séparateur, tronqué à 20 caractères. */
    public static function codeDepuis(string $libelle): string
    {
        return Str::limit(Str::slug($libelle, ''), self::CODE_MAX, '');
    }

    /**
     * Tous les métiers, par libellé, avec le nombre de prestataires rattachés (tous statuts de compte confondus).
     *
     * @return Collection<int, Metier>
     */
    public function lister(): Collection
    {
        $nombres = DB::table('prestataires')->selectRaw('metier, COUNT(*) AS nombre')->groupBy('metier')->pluck('nombre', 'metier');

        return Metier::query()->orderBy('libelle')->get()->each(
            fn (Metier $metier) => $metier->setAttribute('nombre_prestataires', (int) ($nombres[$metier->code] ?? 0))
        );
    }

    /** @throws ReservationImpossibleException 409 si le code existe déjà (y compris si deux créations arrivent en même temps) */
    public function creer(string $libelle): Metier
    {
        $metier = new Metier;
        $metier->code = self::codeDepuis($libelle);
        $metier->libelle = trim($libelle);
        $metier->actif = true;

        try {
            $metier->save();
        } catch (UniqueConstraintViolationException) {
            throw new ReservationImpossibleException('Un métier portant ce nom existe déjà.', Response::HTTP_CONFLICT);
        }

        return $metier->setAttribute('nombre_prestataires', 0);
    }

    /**
     * Renomme le libellé et/ou active ou désactive le métier.
     *
     * @throws ReservationImpossibleException 404 (inconnu), 409 (dernier métier actif)
     */
    public function modifier(string $id, ?string $libelle, ?bool $actif): Metier
    {
        return DB::transaction(function () use ($id, $libelle, $actif) {
            $metier = Str::isUuid($id) ? Metier::query()->lockForUpdate()->find($id) : null;
            if ($metier === null) {
                throw new ReservationImpossibleException('Métier introuvable.', Response::HTTP_NOT_FOUND);
            }

            if ($actif === false && $metier->actif && ! Metier::query()->actifs()->whereKeyNot($metier->id)->exists()) {
                throw new ReservationImpossibleException('Au moins un métier doit rester actif.', Response::HTTP_CONFLICT);
            }

            if ($libelle !== null) {
                $metier->libelle = trim($libelle);
            }
            if ($actif !== null) {
                $metier->actif = $actif;
            }
            $metier->save();

            $nombre = DB::table('prestataires')->where('metier', $metier->code)->count();

            return $metier->setAttribute('nombre_prestataires', $nombre);
        });
    }
}
