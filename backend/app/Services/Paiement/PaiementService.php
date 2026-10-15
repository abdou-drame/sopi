<?php

namespace App\Services\Paiement;

use App\Enums\StatutRetrait;
use App\Exceptions\EvenementPaiementIllisible;

/**
 * Isolation du prestataire de paiement (DexPay en production, faux en test et en démonstration).
 * Les montants sont des chaînes décimales en FCFA (« 3000.00 »), toujours calculés par le serveur (CalculPaiement).
 */
interface PaiementService
{
    /**
     * Ouvre une session de paiement de l'acompte d'une réservation ; renvoie la référence du prestataire et la page de paiement.
     * $paiementId sert à construire la référence marchande (unique) ; à défaut, le pilote en fabrique une.
     */
    public function creerSession(string $reservationId, string $montantAcompte, string $description, ?string $paiementId = null): SessionPaiement;

    /**
     * Vrai seulement si la signature (HMAC-SHA256 hexadécimal du corps brut) est valide. Faux si le secret est vide ou si la
     * signature est absente. À appeler AVANT toute lecture du corps.
     */
    public function verifierSignature(string $corpsBrut, ?string $signature): bool;

    /**
     * Lit l'événement du webhook : nature, référence, résultat et montant. Un événement sans effet est renvoyé avec la nature « ignoré ».
     *
     * @throws EvenementPaiementIllisible si le corps est illisible ou incomplet
     */
    public function analyserEvenement(string $corpsBrut): EvenementPaiement;

    /** Rembourse un paiement réussi (intégralement). Ne lève pas d'exception pour un refus : succes = false. */
    public function rembourser(string $reference, string $montant): ResultatOperation;

    /**
     * Verse un montant à un destinataire (retraits du prestataire, M7).
     *
     * Résultat : succes = true et enCours = false (versement terminé), succes = true et enCours = true (accepté, résultat par webhook),
     * succes = false (refus définitif certain). Toute exception signifie un résultat INCERTAIN : l'appelant ne recrédite rien.
     */
    public function verser(string $montant, string $destinataire, ?ContexteVersement $contexte = null): ResultatOperation;

    /**
     * Interroge l'état d'un versement par sa référence marchande. EnCours (en cours de traitement), Reussi ou Echoue ;
     * null si l'état est inconnu ou illisible (aucune décision ne doit alors être prise).
     */
    public function consulterVersement(string $referenceMarchande): ?StatutRetrait;
}
