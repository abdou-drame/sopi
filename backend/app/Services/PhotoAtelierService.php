<?php

namespace App\Services;

use App\Models\Prestataire;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Photos d'atelier d'un prestataire (RF05). Les fichiers sont sur le disque privé, sous « photos/{prestataire} »,
 * avec un nom aléatoire (UUID + extension tirée du contenu réel). La colonne `photos_atelier` ne contient que ces
 * noms de fichiers, jamais de chemin ; l'identifiant public d'une photo est son nom sans extension.
 */
class PhotoAtelierService
{
    public const NB_MAX = 6;

    /** Extension déduite du contenu réel (jamais du nom d'origine). */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @return list<string> Identifiants des photos, dans l'ordre d'ajout. */
    public function lister(Prestataire $prestataire): array
    {
        return array_map($this->identifiant(...), $this->noms($prestataire));
    }

    /** Ajoute une photo. Renvoie son identifiant, ou null si la limite est atteinte. */
    public function ajouter(Prestataire $prestataire, UploadedFile $fichier): ?string
    {
        $extension = self::EXTENSIONS[$fichier->getMimeType()] ?? 'bin';
        $nom = Str::uuid().'.'.$extension;
        $chemin = $this->dossier($prestataire).'/'.$nom;

        try {
            return DB::transaction(function () use ($prestataire, $fichier, $nom) {
                // Verrou sur la ligne : deux envois simultanés ne peuvent pas dépasser la limite.
                $courant = Prestataire::query()->lockForUpdate()->findOrFail($prestataire->id);
                $noms = $this->noms($courant);

                if (count($noms) >= self::NB_MAX) {
                    return null;
                }

                Storage::disk(InscriptionPrestataireService::DISQUE)->putFileAs($this->dossier($courant), $fichier, $nom);

                $courant->photos_atelier = [...$noms, $nom];
                $courant->save();
                $prestataire->photos_atelier = $courant->photos_atelier;

                return $this->identifiant($nom);
            });
        } catch (Throwable $erreur) {
            Storage::disk(InscriptionPrestataireService::DISQUE)->delete($chemin);

            throw $erreur;
        }
    }

    /** Supprime l'entrée et le fichier. Renvoie false si la photo n'appartient pas à ce prestataire. */
    public function supprimer(Prestataire $prestataire, string $photo): bool
    {
        return DB::transaction(function () use ($prestataire, $photo) {
            $courant = Prestataire::query()->lockForUpdate()->findOrFail($prestataire->id);
            $noms = $this->noms($courant);
            $nom = $this->trouverNom($noms, $photo);

            if ($nom === null) {
                return false;
            }

            $courant->photos_atelier = array_values(array_diff($noms, [$nom]));
            $courant->save();
            $prestataire->photos_atelier = $courant->photos_atelier;

            Storage::disk(InscriptionPrestataireService::DISQUE)->delete($this->dossier($courant).'/'.$nom);

            return true;
        });
    }

    /** Flux de l'image avec son Content-Type réel, ou null si la photo est inconnue ou son fichier absent. */
    public function flux(Prestataire $prestataire, string $photo): ?StreamedResponse
    {
        $nom = $this->trouverNom($this->noms($prestataire), $photo);
        if ($nom === null) {
            return null;
        }

        $disque = Storage::disk(InscriptionPrestataireService::DISQUE);
        $chemin = $this->dossier($prestataire).'/'.$nom;
        if (! $disque->exists($chemin)) {
            return null;
        }

        $type = $disque->mimeType($chemin);
        if (! in_array($type, array_keys(self::EXTENSIONS), true)) {
            return null;
        }

        return $disque->response($chemin, $nom, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], 'inline');
    }

    public function identifiant(string $nom): string
    {
        return pathinfo($nom, PATHINFO_FILENAME);
    }

    /** @return list<string> */
    private function noms(Prestataire $prestataire): array
    {
        return array_values($prestataire->photos_atelier ?? []);
    }

    /** @param  list<string>  $noms */
    private function trouverNom(array $noms, string $photo): ?string
    {
        foreach ($noms as $nom) {
            if ($this->identifiant($nom) === $photo) {
                return $nom;
            }
        }

        return null;
    }

    private function dossier(Prestataire $prestataire): string
    {
        return 'photos/'.$prestataire->id;
    }
}
