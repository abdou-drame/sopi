<?php

namespace App\Services\Paiement;

use App\Enums\StatutPaiement;
use App\Exceptions\EvenementPaiementIllisible;
use JsonException;

/**
 * Faux prestataire de paiement (PAYMENT_DRIVER=fake) : aucun appel réseau, pour les tests et la démonstration.
 *
 * Corps du faux webhook : {"reference":"FAKE-...","statut":"reussi"|"echoue","montant":"3000.00"}, signé par
 * HMAC-SHA256 (hexadécimal) du corps brut avec le secret de configuration.
 */
class FakePaiementService implements PaiementService
{
    public function __construct(private readonly ?string $secret = null) {}

    public function creerSession(string $reservationId, string $montantAcompte, string $description): SessionPaiement
    {
        $reference = $this->reference('');

        // Domaine en .invalid : jamais routable.
        return new SessionPaiement($reference, 'https://paiement.fake.invalid/'.$reference);
    }

    public function verifierSignature(string $corpsBrut, ?string $signature): bool
    {
        if ($this->secret === null || $this->secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $corpsBrut, $this->secret), $signature);
    }

    public function analyserEvenement(string $corpsBrut): EvenementPaiement
    {
        try {
            $donnees = json_decode($corpsBrut, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new EvenementPaiementIllisible('Le corps de l\'événement de paiement est illisible.');
        }

        if (! is_array($donnees)) {
            throw new EvenementPaiementIllisible('Le corps de l\'événement de paiement est illisible.');
        }

        $reference = $donnees['reference'] ?? null;
        $statut = match ($donnees['statut'] ?? null) {
            StatutPaiement::Reussi->value => StatutPaiement::Reussi,
            StatutPaiement::Echoue->value => StatutPaiement::Echoue,
            default => null,
        };
        $montant = $this->montant($donnees['montant'] ?? null);

        if (! is_string($reference) || $reference === '' || $statut === null || $montant === null) {
            throw new EvenementPaiementIllisible('L\'événement de paiement est incomplet ou invalide.');
        }

        return new EvenementPaiement($reference, $statut, $montant);
    }

    public function rembourser(string $reference, string $montant): ResultatOperation
    {
        return $this->operation($reference !== '' && $this->montant($montant) !== null, 'RMB-');
    }

    public function verser(string $montant, string $destinataire): ResultatOperation
    {
        return $this->operation($destinataire !== '' && $this->montant($montant) !== null, 'VRS-');
    }

    private function operation(bool $valide, string $type): ResultatOperation
    {
        return $valide ? new ResultatOperation(true, $this->reference($type)) : new ResultatOperation(false);
    }

    private function reference(string $type): string
    {
        return 'FAKE-'.$type.strtoupper(bin2hex(random_bytes(8)));
    }

    /** Montant strictement positif, au plus 2 décimales, renvoyé au format « 3000.00 » ; null sinon. */
    private function montant(mixed $valeur): ?string
    {
        if (is_float($valeur)) {
            $valeur = sprintf('%.2f', $valeur);
        }
        if (is_int($valeur)) {
            $valeur = (string) $valeur;
        }
        if (! is_string($valeur) || preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $valeur, $m) !== 1) {
            return null;
        }

        $centimes = ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '', 2, '0');
        if ($centimes <= 0) {
            return null;
        }

        return intdiv($centimes, 100).'.'.str_pad((string) ($centimes % 100), 2, '0', STR_PAD_LEFT);
    }
}
