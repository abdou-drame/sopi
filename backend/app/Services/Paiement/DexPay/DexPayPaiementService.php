<?php

namespace App\Services\Paiement\DexPay;

use App\Enums\StatutPaiement;
use App\Enums\StatutRetrait;
use App\Exceptions\ConfigurationPaiementInvalide;
use App\Exceptions\DexPayErreur;
use App\Exceptions\EvenementPaiementIllisible;
use App\Services\Paiement\ContexteVersement;
use App\Services\Paiement\EvenementPaiement;
use App\Services\Paiement\GenreEvenement;
use App\Services\Paiement\PaiementService;
use App\Services\Paiement\ResultatOperation;
use App\Services\Paiement\SessionPaiement;
use Illuminate\Support\Str;
use JsonException;

/**
 * Pilote DexPay (PAYMENT_DRIVER=dexpay), d'après la documentation officielle https://docs.dexpay.africa.
 *
 * - Montants : FCFA entiers (XOF n'a pas de décimales), multipliés par DEXPAY_AMOUNT_FACTOR (1 par défaut).
 * - Webhook : en-tête X-Webhook-Signature = HMAC-SHA256 hexadécimal du corps brut, avec la clé secrète de l'API.
 * - Versement : 201 « completed » = terminé ; « pending/processing » = en cours ; refus certain = succes false ; tout le reste
 *   lève une exception (résultat incertain, rien n'est recrédité).
 * Ne journalise jamais de clé, de corps ni de numéro.
 */
class DexPayPaiementService implements PaiementService
{
    /** Codes HTTP qui prouvent que le versement n'a pas été créé (refus certain). */
    private const REFUS_VERSEMENT = [400, 401, 402, 403, 404, 422, 503];

    public function __construct(
        private readonly DexPayClient $client,
        private readonly string $cleSecrete,
        private readonly string $pays = 'SN',
        private readonly int $facteurMontant = 1,
    ) {}

    public static function depuisConfiguration(): self
    {
        $client = DexPayClient::depuisConfiguration();
        $config = (array) config('sopi.paiement.dexpay');
        $facteur = (int) ($config['amount_factor'] ?? 1);
        if ($facteur < 1) {
            throw new ConfigurationPaiementInvalide('DEXPAY_AMOUNT_FACTOR doit être un entier supérieur ou égal à 1.');
        }

        return new self($client, trim((string) ($config['secret_key'] ?? '')), (string) ($config['country'] ?? 'SN'), $facteur);
    }

    public function creerSession(string $reservationId, string $montantAcompte, string $description, ?string $paiementId = null): SessionPaiement
    {
        $reference = 'SOPI-PAY-'.($paiementId ?? Str::uuid()->toString());
        $reponse = $this->client->post('/checkout-sessions', [
            'reference' => $reference,
            'item_name' => $description,
            'amount' => $this->versDexPay($montantAcompte),
            'currency' => 'XOF',
            'success_url' => $this->urlRetour('success_url', '/paiement/succes'),
            'failure_url' => $this->urlRetour('failure_url', '/paiement/echec'),
            'webhook_url' => $this->urlWebhook(),
            'is_one_shot_payment' => true,
            'metadata' => ['paiement_id' => $paiementId],
        ]);

        if (! $reponse->reussie()) {
            throw new DexPayErreur('Création de la session de paiement refusée.', $reponse->statutHttp, $reponse->extrait);
        }

        $url = $reponse->texte('payment_url');
        // Bac à sable : la page de test remplace la page de paiement.
        if (($reponse->donnees['isSandbox'] ?? false) === true && $reponse->texte('sandbox_payment_url') !== null) {
            $url = $reponse->texte('sandbox_payment_url');
        }
        if ($url === null) {
            throw new DexPayErreur('Réponse de création de session illisible.', $reponse->statutHttp, $reponse->extrait);
        }

        return new SessionPaiement($reponse->texte('reference') ?? $reference, $url);
    }

    public function verifierSignature(string $corpsBrut, ?string $signature): bool
    {
        if ($this->cleSecrete === '' || $signature === null || trim($signature) === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $corpsBrut, $this->cleSecrete), strtolower(trim($signature)));
    }

    public function analyserEvenement(string $corpsBrut): EvenementPaiement
    {
        try {
            $donnees = json_decode($corpsBrut, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new EvenementPaiementIllisible('Le corps de l\'événement de paiement est illisible.');
        }
        $type = is_array($donnees) ? ($donnees['event'] ?? null) : null;
        if (! is_string($type) || $type === '') {
            throw new EvenementPaiementIllisible('L\'événement de paiement est incomplet ou invalide.');
        }

        $evenement = match ($type) {
            'checkout.completed' => $this->evenementPaiement($donnees, StatutPaiement::Reussi),
            'checkout.failed', 'checkout.cancelled' => $this->evenementPaiement($donnees, StatutPaiement::Echoue),
            'checkout.refunded' => $this->evenementRemboursement($donnees),
            'payout.completed' => $this->evenementVersement($donnees, StatutRetrait::Reussi),
            'payout.failed', 'payout.cancelled' => $this->evenementVersement($donnees, StatutRetrait::Echoue),
            default => EvenementPaiement::ignore(),
        };

        // Le type n'est gardé pour le journal que sous une forme inoffensive (lettres, points, tirets bas).
        return $evenement->avecType(preg_match('/^[a-z0-9._-]{1,40}$/i', $type) === 1 ? $type : 'inconnu');
    }

    public function rembourser(string $reference, string $montant): ResultatOperation
    {
        if ($reference === '') {
            return new ResultatOperation(false, null, false, 'reference_vide');
        }

        // Remboursement intégral uniquement : le montant n'est pas envoyé.
        try {
            $reponse = $this->client->post('/checkout-sessions/refund/'.rawurlencode($reference), null, true);
        } catch (DexPayErreur $e) {
            return new ResultatOperation(false, null, false, 'injoignable', null, $e->extrait);
        }

        if (! $reponse->reussie()) {
            return new ResultatOperation(false, null, false, 'http_'.$reponse->statutHttp, $reponse->statutHttp, $reponse->extrait);
        }

        return $reponse->statut() === 'success'
            ? new ResultatOperation(true, $reference)
            : new ResultatOperation(false, null, false, 'statut_'.($reponse->statut() ?? 'inconnu'), $reponse->statutHttp, $reponse->extrait);
    }

    public function verser(string $montant, string $destinataire, ?ContexteVersement $contexte = null): ResultatOperation
    {
        if ($contexte === null || $destinataire === '') {
            return new ResultatOperation(false, null, false, 'contexte_manquant');
        }

        $reference = ContexteVersement::referenceDe($contexte->retraitId);
        $reponse = $this->client->post('/payouts', [
            'reference' => $reference,
            'amount' => $this->versDexPay($montant),
            'currency' => 'XOF',
            'destination_phone' => $destinataire,
            'destination_details' => $this->destination($contexte),
            'webhook_url' => $this->urlWebhook(),
            'metadata' => ['retrait_id' => $contexte->retraitId],
        ], true, ['Idempotency-Key' => $contexte->retraitId]);

        if (in_array($reponse->statutHttp, self::REFUS_VERSEMENT, true)) {
            return new ResultatOperation(false, null, false, 'http_'.$reponse->statutHttp, $reponse->statutHttp, $reponse->extrait);
        }
        if ($reponse->statutHttp !== 200 && $reponse->statutHttp !== 201) {
            // 409 (référence déjà utilisée), 5xx, 429… : on ne sait pas si le versement existe.
            throw new DexPayErreur('Résultat du versement incertain.', $reponse->statutHttp, $reponse->extrait);
        }

        $reference = $reponse->texte('reference') ?? $reference;

        return match ($reponse->statut()) {
            'completed' => new ResultatOperation(true, $reference),
            'pending', 'processing', 'frozen', 'initiated' => new ResultatOperation(true, $reference, true),
            'failed', 'cancelled' => new ResultatOperation(false, $reference, false, 'statut_'.$reponse->statut(), $reponse->statutHttp, $reponse->extrait),
            default => throw new DexPayErreur('Réponse de versement illisible.', $reponse->statutHttp, $reponse->extrait),
        };
    }

    public function consulterVersement(string $referenceMarchande): ?StatutRetrait
    {
        try {
            $reponse = $this->client->get('/payouts/'.rawurlencode($referenceMarchande));
        } catch (DexPayErreur) {
            return null;
        }
        if ($reponse->statutHttp !== 200) {
            return null;
        }

        return match ($reponse->statut()) {
            'completed' => StatutRetrait::Reussi,
            'failed', 'cancelled' => StatutRetrait::Echoue,
            'pending', 'processing', 'frozen', 'initiated' => StatutRetrait::EnCours,
            default => null,
        };
    }

    /** @return array<string, string> */
    private function destination(ContexteVersement $contexte): array
    {
        $code = (string) config('sopi.paiement.dexpay.operateurs.'.$contexte->operateur->value);
        $details = [];
        // Un seul endroit décide quels champs portent l'opérateur (DEXPAY_PAYOUT_OPERATOR_FIELDS : « operator », « operator,provider_short_name »…).
        foreach (array_filter(array_map('trim', explode(',', (string) config('sopi.paiement.dexpay.payout_operator_fields', 'operator')))) as $champ) {
            $details[$champ] = $code;
        }
        $details['countryISO'] = $this->pays;
        if ($contexte->nomBeneficiaire !== null && trim($contexte->nomBeneficiaire) !== '') {
            $details['recipient_name'] = trim($contexte->nomBeneficiaire);
        }

        return $details;
    }

    /** @param array<string, mixed> $donnees */
    private function evenementPaiement(array $donnees, StatutPaiement $statut): EvenementPaiement
    {
        $reference = $this->texte($donnees, 'reference');
        $montant = $this->depuisDexPay($donnees['amount'] ?? null);
        $devise = $donnees['currency'] ?? 'XOF';
        if ($reference === null || $montant === null || $devise !== 'XOF') {
            throw new EvenementPaiementIllisible('L\'événement de paiement est incomplet ou invalide.');
        }

        return new EvenementPaiement($reference, $statut, $montant);
    }

    /** @param array<string, mixed> $donnees */
    private function evenementRemboursement(array $donnees): EvenementPaiement
    {
        $reference = $this->texte($donnees, 'reference');
        if ($reference === null) {
            throw new EvenementPaiementIllisible('L\'événement de remboursement est incomplet ou invalide.');
        }

        return new EvenementPaiement($reference, StatutPaiement::Rembourse, $this->depuisDexPay($donnees['amount'] ?? null) ?? '0.00', GenreEvenement::Remboursement);
    }

    /** @param array<string, mixed> $donnees */
    private function evenementVersement(array $donnees, StatutRetrait $statut): EvenementPaiement
    {
        $reference = $this->texte($donnees, 'reference');
        $marchande = $this->texte($donnees, 'merchant_reference');
        $metadata = is_array($donnees['metadata'] ?? null) ? $donnees['metadata'] : [];
        $retraitId = $this->texte($metadata, 'retrait_id');
        if ($reference === null && $marchande === null && $retraitId === null) {
            throw new EvenementPaiementIllisible('L\'événement de versement est incomplet ou invalide.');
        }

        return new EvenementPaiement(
            $reference ?? '',
            StatutPaiement::EnAttente,
            $this->depuisDexPay($donnees['amount'] ?? null) ?? '0.00',
            GenreEvenement::Versement,
            $statut,
            $marchande,
            $retraitId,
        );
    }

    /** @param array<string, mixed> $donnees */
    private function texte(array $donnees, string $cle): ?string
    {
        $valeur = $donnees[$cle] ?? null;

        return is_string($valeur) && trim($valeur) !== '' ? trim($valeur) : null;
    }

    /** « 3000.00 » (FCFA) vers le nombre entier attendu par DexPay (FCFA x facteur). Un montant avec fraction de franc est refusé. */
    private function versDexPay(string $montant): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $montant, $m) !== 1) {
            throw new DexPayErreur('Montant invalide.');
        }
        $centimes = ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '', 2, '0');
        if ($centimes <= 0 || $centimes % 100 !== 0) {
            throw new DexPayErreur('Montant invalide : le FCFA n\'a pas de décimales.');
        }

        return intdiv($centimes, 100) * $this->facteurMontant;
    }

    /** Montant reçu de DexPay (nombre ou texte, selon le facteur) vers « 3000.00 » (FCFA) ; null s'il est absent, nul ou illisible. */
    private function depuisDexPay(mixed $valeur): ?string
    {
        if (is_string($valeur) && ! is_numeric($valeur)) {
            return null;
        }
        if (! is_int($valeur) && ! is_float($valeur) && ! is_string($valeur)) {
            return null;
        }
        $centimes = (int) round(((float) $valeur) * 100 / $this->facteurMontant);
        if ($centimes <= 0) {
            return null;
        }

        return intdiv($centimes, 100).'.'.str_pad((string) ($centimes % 100), 2, '0', STR_PAD_LEFT);
    }

    private function urlWebhook(): string
    {
        // Construite depuis la configuration, jamais depuis la requête entrante.
        return rtrim((string) config('app.url'), '/').'/api/v1/webhooks/paiement';
    }

    private function urlRetour(string $cle, string $defaut): string
    {
        $configuree = config('sopi.paiement.dexpay.'.$cle);

        return is_string($configuree) && $configuree !== '' ? $configuree : rtrim((string) config('app.url'), '/').$defaut;
    }
}
