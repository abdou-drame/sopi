<?php

namespace App\Services;

use App\Models\Prestataire;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Retrouve un dossier d'inscription à partir de son jeton de suivi (RF06, RF07).
 * Seul le hachage SHA-256 du jeton remis à l'inscription est en base ; le jeton reçu n'est jamais enregistré ni journalisé.
 *
 * Un second jeton, signé, accompagne l'e-mail du résultat de l'examen (mémoire, 3.2.2 : « avec le lien de suivi ») : le jeton
 * de l'inscription n'existe plus en clair, il ne peut donc pas être renvoyé. Il est dérivé de l'identifiant du dossier et de la
 * clé de l'application (HMAC-SHA256) : rien de plus n'est stocké, et il ne peut pas être forgé sans la clé.
 * Les deux jetons ouvrent le même suivi, qui ne contient aucune donnée personnelle.
 */
class SuiviDossierService
{
    /** Lien personnel de suivi : la page web publique GET /suivi/{jeton} (l'API JSON /api/v1/suivi/{jeton} reste pour le mobile). */
    public static function lien(string $jeton): string
    {
        return rtrim(config('app.url'), '/').'/suivi/'.$jeton;
    }

    /** Jeton signé du dossier, pour le lien de suivi de l'e-mail de résultat. C'est toujours le même pour un dossier donné. */
    public function jetonDuResultat(Prestataire $prestataire): string
    {
        $id = (string) $prestataire->getKey();

        return $id.'.'.$this->signature($id);
    }

    public function trouverParJeton(string $jeton): ?Prestataire
    {
        return $this->trouverParJetonSigne($jeton) ?? $this->trouverParHachage($jeton);
    }

    private function trouverParHachage(string $jeton): ?Prestataire
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

    /** Jeton « identifiant.signature » : l'identifiant doit être un UUID et la signature exacte, avant toute requête en base. */
    private function trouverParJetonSigne(string $jeton): ?Prestataire
    {
        [$id, $signature] = array_pad(explode('.', $jeton, 2), 2, '');

        if (! Str::isUuid($id) || (string) config('app.key') === '' || ! hash_equals($this->signature($id), $signature)) {
            return null;
        }

        return Prestataire::with(['user', 'justificatifs'])->find($id);
    }

    private function signature(string $id): string
    {
        $cle = (string) config('app.key');
        if ($cle === '') {
            // Une signature calculée sans clé pourrait être refaite par n'importe qui.
            throw new RuntimeException('APP_KEY est vide : impossible de signer un lien de suivi.');
        }

        return hash_hmac('sha256', 'suivi-dossier|'.$id, $cle);
    }
}
