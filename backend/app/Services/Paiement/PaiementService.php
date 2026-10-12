<?php

namespace App\Services\Paiement;

use App\Exceptions\EvenementPaiementIllisible;

/**
 * Isolation du prestataire de paiement (DexPay en production, faux en test et en démonstration).
 * Les montants sont des chaînes décimales en FCFA (« 3000.00 »), toujours calculés par le serveur (CalculPaiement).
 */
interface PaiementService
{
    /** Ouvre une session de paiement de l'acompte d'une réservation ; renvoie la référence du prestataire et la page de paiement. */
    public function creerSession(string $reservationId, string $montantAcompte, string $description): SessionPaiement;

    /**
     * Vrai seulement si la signature (HMAC-SHA256 hexadécimal du corps brut) est valide. Faux si le secret est vide ou si la
     * signature est absente. À appeler AVANT toute lecture du corps.
     */
    public function verifierSignature(string $corpsBrut, ?string $signature): bool;

    /**
     * Lit l'événement du webhook : référence, résultat (REUSSI ou ECHOUE) et montant.
     *
     * @throws EvenementPaiementIllisible si le corps est illisible ou incomplet
     */
    public function analyserEvenement(string $corpsBrut): EvenementPaiement;

    /** Rembourse un paiement réussi (en tout ou partie). */
    public function rembourser(string $reference, string $montant): ResultatOperation;

    /** Verse un montant à un destinataire (retraits du prestataire, M7). */
    public function verser(string $montant, string $destinataire): ResultatOperation;
}
