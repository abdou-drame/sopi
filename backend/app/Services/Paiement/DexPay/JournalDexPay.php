<?php

namespace App\Services\Paiement\DexPay;

use App\Exceptions\ConfigurationPaiementInvalide;
use App\Exceptions\DexPayErreur;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Journal des échecs DexPay. Contexte sûr uniquement : nom de l'événement, identifiant du paiement ou du retrait, classe de
 * l'exception, code HTTP renvoyé par DexPay et un court extrait de SA réponse (jamais de notre requête), dont tout ce qui
 * ressemble à une clé, un jeton, une signature ou un numéro est masqué. Jamais les clés, les en-têtes ni le corps envoyé.
 */
final class JournalDexPay
{
    public const EXTRAIT_MAX = 300;

    private const MASQUE = '[masqué]';

    private const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /** @param array<string, scalar|null> $base identifiants (paiement_id, retrait_id…) */
    public static function erreur(string $evenement, string $message, array $base, ?Throwable $e = null, ?int $codeHttp = null, string $extrait = ''): void
    {
        Log::error($evenement.' : '.$message, self::contexte($evenement, $base, $e, $codeHttp, $extrait));
    }

    /**
     * @param  array<string, scalar|null>  $base
     * @return array<string, mixed>
     */
    public static function contexte(string $evenement, array $base, ?Throwable $e = null, ?int $codeHttp = null, string $extrait = ''): array
    {
        $contexte = ['evenement' => $evenement] + $base;

        if ($e instanceof DexPayErreur) {
            $codeHttp ??= $e->statutHttp;
            $extrait = $extrait !== '' ? $extrait : $e->extrait;
        }
        if ($e !== null) {
            $contexte['exception'] = $e::class;
        }
        if ($e instanceof ConfigurationPaiementInvalide && $e->variablesManquantes !== []) {
            // Seulement les NOMS des variables, jamais leurs valeurs.
            $contexte['variables_manquantes'] = $e->variablesManquantes;
        }
        if ($codeHttp !== null) {
            $contexte['code_http'] = $codeHttp;
        }
        if ($extrait !== '') {
            $contexte['extrait'] = self::extrait($extrait);
        }

        return $contexte;
    }

    /** Extrait masqué et borné (300 caractères au plus) d'un corps de réponse DexPay. */
    public static function extrait(string $corps): string
    {
        $texte = trim((string) preg_replace('/\s+/u', ' ', self::masquer($corps)));

        return mb_substr($texte, 0, self::EXTRAIT_MAX);
    }

    /** Masque les clés configurées et tout ce qui y ressemble (clés, jetons, signatures, longues chaînes, numéros). */
    public static function masquer(string $texte): string
    {
        // 1. Les valeurs réellement configurées.
        foreach (['public_key', 'secret_key'] as $cle) {
            $valeur = config('sopi.paiement.dexpay.'.$cle);
            if (is_string($valeur) && strlen(trim($valeur)) >= 4) {
                $texte = str_replace(trim($valeur), self::MASQUE, $texte);
            }
        }

        // 2. Les champs dont le nom est sensible (JSON ou clé=valeur).
        $texte = (string) preg_replace(
            '/("?(?:api[_-]?key|secret|token|authorization|signature|password|mot_de_passe|x-api-[a-z]+)[a-z_-]*"?\s*[:=]\s*)("[^"]*"|[^\s,;}&]+)/i',
            '$1"'.self::MASQUE.'"',
            $texte,
        );

        // 3. Jetons « Bearer ».
        $texte = (string) preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer '.self::MASQUE, $texte);

        // 4. Clés au format préfixe_valeur (pk_…, sk_…, key-…).
        $texte = (string) preg_replace('/\b(?:pk|sk|rk|key|sec|live|test)[_-][A-Za-z0-9_-]{6,}/i', self::MASQUE, $texte);

        // 5. Longues chaînes opaques (hexadécimal, base64), sauf nos identifiants UUID.
        $texte = (string) preg_replace_callback(
            '/[A-Za-z0-9_+\/=-]{32,}/',
            fn (array $m) => preg_match('/'.self::UUID.'/i', $m[0]) === 1 ? $m[0] : self::MASQUE,
            $texte,
        );

        // 6. Numéros de téléphone (8 chiffres ou plus, séparateurs tolérés).
        return (string) preg_replace('/\+?\d[\d\s.-]{6,}\d/', '[numéro masqué]', $texte);
    }
}
