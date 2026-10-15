<?php

namespace App\Services\Paiement\DexPay;

use App\Exceptions\ConfigurationPaiementInvalide;
use App\Exceptions\DexPayErreur;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client HTTP de l'API DexPay. Aucun nouvel essai automatique (une création ne doit jamais partir deux fois) et aucune redirection suivie.
 * La clé publique voyage dans x-api-key ; la clé secrète (x-api-secret) n'est envoyée que là où la documentation l'exige
 * (remboursement, versement). Jamais de clé dans un message d'erreur ni dans le journal.
 */
class DexPayClient
{
    public const URL_BASE_PAR_DEFAUT = 'https://api.dexpay.africa/api/v1';

    public function __construct(
        private readonly string $urlBase,
        private readonly string $clePublique,
        private readonly string $cleSecrete,
        private readonly int $delaiSecondes,
    ) {}

    public static function depuisConfiguration(): self
    {
        $config = (array) config('sopi.paiement.dexpay');
        $manquantes = [];
        foreach (['public_key' => 'DEXPAY_PUBLIC_KEY', 'secret_key' => 'DEXPAY_SECRET_KEY'] as $cle => $variable) {
            if (! is_string($config[$cle] ?? null) || trim($config[$cle]) === '') {
                $manquantes[] = $variable;
            }
        }
        if ($manquantes !== []) {
            throw new ConfigurationPaiementInvalide('La configuration DexPay est incomplète : variable(s) manquante(s) '.implode(', ', $manquantes).'.', $manquantes);
        }

        return new self(
            self::urlBase($config['base_url'] ?? null),
            trim($config['public_key']),
            trim($config['secret_key']),
            max(1, (int) ($config['timeout_seconds'] ?? 15)),
        );
    }

    /**
     * Adresse de base : absente ou vide = adresse de production par défaut ; fournie = https:// obligatoire, pas de « / » final.
     * Le message ne cite jamais la valeur reçue.
     */
    private static function urlBase(mixed $valeur): string
    {
        $url = is_string($valeur) ? trim($valeur) : '';
        if ($url === '') {
            return self::URL_BASE_PAR_DEFAUT;
        }
        if (! str_starts_with($url, 'https://') || strlen($url) <= 8 || str_ends_with($url, '/') || preg_match('/\s/', $url)) {
            throw new ConfigurationPaiementInvalide('DEXPAY_BASE_URL est invalide : elle doit commencer par https:// et ne pas se terminer par « / » (exemple : '.self::URL_BASE_PAR_DEFAUT.'). Laissez-la vide pour utiliser l\'adresse par défaut.', ['DEXPAY_BASE_URL']);
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>|null  $corps  null = aucun corps
     * @param  array<string, string>  $entetes
     *
     * @throws DexPayErreur service injoignable ou trop lent
     */
    public function post(string $chemin, ?array $corps, bool $avecSecret = false, array $entetes = []): ReponseDexPay
    {
        return $this->envoyer(fn (PendingRequest $requete) => $requete->withHeaders($entetes)->post($chemin, $corps), $avecSecret);
    }

    /** @throws DexPayErreur service injoignable ou trop lent */
    public function get(string $chemin, bool $avecSecret = true): ReponseDexPay
    {
        return $this->envoyer(fn (PendingRequest $requete) => $requete->get($chemin), $avecSecret);
    }

    private function envoyer(callable $appel, bool $avecSecret): ReponseDexPay
    {
        $entetes = ['x-api-key' => $this->clePublique];
        if ($avecSecret) {
            $entetes['x-api-secret'] = $this->cleSecrete;
        }

        try {
            $reponse = $appel(
                Http::baseUrl($this->urlBase)
                    ->timeout($this->delaiSecondes)
                    ->connectTimeout($this->delaiSecondes)
                    ->withoutRedirecting()
                    ->acceptJson()
                    ->asJson()
                    ->withHeaders($entetes)
            );
        } catch (ConnectionException $e) {
            // Le texte de l'erreur réseau (résolution DNS, délai, TLS) aide au diagnostic ; il ne contient pas les en-têtes et est masqué.
            throw new DexPayErreur('DexPay est injoignable ou n\'a pas répondu à temps.', null, JournalDexPay::extrait($e->getMessage()));
        }

        return $this->lire($reponse);
    }

    private function lire(Response $reponse): ReponseDexPay
    {
        $json = $reponse->json();
        $json = is_array($json) ? $json : [];
        $donnees = is_array($json['data'] ?? null) ? $json['data'] : $json;

        // Le message d'erreur éventuel sert à décider, jamais à être affiché tel quel ; seul un extrait masqué part au journal d'un échec.
        return new ReponseDexPay($reponse->status(), $donnees, JournalDexPay::extrait($reponse->body()));
    }
}
