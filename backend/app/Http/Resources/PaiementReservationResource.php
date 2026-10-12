<?php

namespace App\Http\Resources;

use App\Models\Paiement;
use App\Services\Paiement\CalculPaiement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Paiement de l'acompte vu par le client. N'expose NI la commission, NI la référence du prestataire de paiement, NI le code de
 * réservation, NI aucune donnée personnelle. Les montants sont des chaînes décimales en FCFA.
 *
 * @mixin Paiement
 */
class PaiementReservationResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $reservation = $this->reservation;

        return [
            'paiement' => [
                'id' => $this->id,
                'statut' => $this->statut->value,
                'montantAcompte' => $this->montant_acompte,
                'devise' => 'XOF',
            ],
            'montantRestantSurPlace' => CalculPaiement::soustraire($reservation->prix_applique, $this->montant_acompte),
            'urlPaiement' => $this->url_paiement,
            'reservation' => [
                'id' => $reservation->id,
                'statut' => $reservation->statut->value,
            ],
        ];
    }
}
