<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Blocage temporaire après des échecs de connexion consécutifs.
 *
 * La clé dépend uniquement du numéro saisi (haché), jamais de l'existence du compte :
 * un numéro inconnu est bloqué exactement comme un numéro connu.
 */
class BlocageConnexionService
{
    /** Secondes restantes de blocage pour ce numéro, ou 0 s'il n'est pas bloqué. */
    public function secondesRestantes(string $telephone): int
    {
        $jusqua = Cache::get($this->cleBlocage($telephone));

        return $jusqua ? max(0, (int) $jusqua - now()->getTimestamp()) : 0;
    }

    /** Enregistre un échec ; déclenche le blocage au seuil. Renvoie true si le numéro vient d'être bloqué. */
    public function enregistrerEchec(string $telephone): bool
    {
        $dureeSecondes = $this->dureeBlocageSecondes();
        $cle = $this->cleEchecs($telephone);

        // Les échecs ne sont « consécutifs » que s'ils se suivent à moins d'une durée de blocage d'intervalle.
        Cache::add($cle, 0, $dureeSecondes);
        $echecs = Cache::increment($cle);

        if ($echecs >= config('securite.connexion.max_echecs')) {
            Cache::put($this->cleBlocage($telephone), now()->getTimestamp() + $dureeSecondes, $dureeSecondes);
            Cache::forget($cle);

            return true;
        }

        // Prolonge la fenêtre à chaque échec (expiration glissante).
        Cache::put($cle, $echecs, $dureeSecondes);

        return false;
    }

    /** Remet le compteur à zéro (connexion réussie). */
    public function reinitialiser(string $telephone): void
    {
        Cache::forget($this->cleEchecs($telephone));
    }

    /** Message français indiquant le délai restant. */
    public function message(int $secondes): string
    {
        $minutes = (int) ceil($secondes / 60);
        $delai = $minutes <= 1 ? 'moins d\'une minute' : "{$minutes} minutes";

        return "Trop d'échecs de connexion. Réessayez dans {$delai}.";
    }

    private function dureeBlocageSecondes(): int
    {
        return config('securite.connexion.duree_blocage_minutes') * 60;
    }

    private function cleEchecs(string $telephone): string
    {
        return 'connexion:echecs:'.sha1(mb_strtolower($telephone));
    }

    private function cleBlocage(string $telephone): string
    {
        return 'connexion:blocage:'.sha1(mb_strtolower($telephone));
    }
}
