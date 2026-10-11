<?php

namespace App\Services;

use App\Models\Prestataire;

/**
 * Retrouve un dossier d'inscription à partir de son jeton de suivi (RF06).
 * Seul le hachage SHA-256 du jeton est en base ; le jeton reçu n'est jamais enregistré ni journalisé.
 */
class SuiviDossierService
{
    public function trouverParJeton(string $jeton): ?Prestataire
    {
        $prestataire = Prestataire::with(['user', 'justificatifs'])
            ->where('jeton_suivi_hash', hash('sha256', $jeton))
            ->first();

        // Comparaison à temps constant en plus de la recherche indexée (défense en profondeur).
        if ($prestataire === null || ! hash_equals($prestataire->jeton_suivi_hash, hash('sha256', $jeton))) {
            return null;
        }

        return $prestataire;
    }
}
