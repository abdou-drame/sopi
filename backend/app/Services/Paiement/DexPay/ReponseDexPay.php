<?php

namespace App\Services\Paiement\DexPay;

/**
 * Réponse de DexPay : code HTTP et contenu JSON. Les réponses sont parfois enveloppées dans « data » : $donnees est le contenu utile.
 */
final readonly class ReponseDexPay
{
    /** @param array<string, mixed> $donnees */
    public function __construct(public int $statutHttp, public array $donnees) {}

    public function reussie(): bool
    {
        return $this->statutHttp >= 200 && $this->statutHttp < 300;
    }

    /** Statut métier de l'objet renvoyé (« completed », « success »…), en minuscules ; null s'il est absent ou illisible. */
    public function statut(): ?string
    {
        $statut = $this->donnees['status'] ?? null;

        return is_string($statut) && $statut !== '' ? strtolower($statut) : null;
    }

    public function texte(string $cle): ?string
    {
        $valeur = $this->donnees[$cle] ?? null;

        return is_string($valeur) && $valeur !== '' ? $valeur : null;
    }
}
