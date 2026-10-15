<?php

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Enums\StatutRetrait;
use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\Retrait;
use App\Models\User;
use App\Services\Paiement\PaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const DW_CLE_SECRETE = 'sk_factice_webhook';

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'sopi.paiement.driver' => 'dexpay',
        'sopi.paiement.dexpay.base_url' => 'https://dexpay.test/api/v1',
        'sopi.paiement.dexpay.public_key' => 'pk_factice_webhook',
        'sopi.paiement.dexpay.secret_key' => DW_CLE_SECRETE,
        'sopi.paiement.webhook_secret' => DW_CLE_SECRETE,
    ]);
    app()->forgetInstance(PaiementService::class);
});

/** Réservation ACCEPTEE (acompte 4 500) avec son paiement EN_ATTENTE et sa référence DexPay. */
function dwPaiement(): Paiement
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $prestation = Prestation::factory()->for($prestataire)->create(['prix' => '15000.00']);
    $debut = now()->addDays(2);
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
    $reservation = Reservation::factory()->acceptee()->create(['client_id' => User::factory()->create()->id, 'creneau_id' => $creneau->id, 'prestation_id' => $prestation->id]);

    return Paiement::factory()->enAttente()->create(['reservation_id' => $reservation->id, 'montant_acompte' => '4500.00', 'reference_dexpay' => 'SOPI-PAY-'.fake()->uuid()]);
}

/** Envoie un événement signé avec la clé secrète (ou une autre, ou aucune signature). */
function dwEnvoyer(array $evenement, ?string $cle = DW_CLE_SECRETE, bool $signer = true): TestResponse
{
    $corps = json_encode($evenement);
    $entetes = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
    if ($signer) {
        $entetes['HTTP_X_WEBHOOK_SIGNATURE'] = hash_hmac('sha256', $corps, (string) $cle);
    }

    return test()->call('POST', '/api/v1/webhooks/paiement', [], [], [], $entetes, $corps);
}

function dwEvenement(string $type, Paiement $paiement, int $montant = 4500): array
{
    return ['event' => $type, 'reference' => $paiement->reference_dexpay, 'amount' => $montant, 'currency' => 'XOF'];
}

describe('événements de paiement', function () {
    it('checkout.completed confirme la réservation et génère le code', function () {
        $paiement = dwPaiement();

        dwEnvoyer(dwEvenement('checkout.completed', $paiement))->assertOk()->assertJsonPath('message', 'Paiement confirmé.');

        $reservation = Reservation::query()->find($paiement->reservation_id);
        expect($paiement->refresh()->statut)->toBe(StatutPaiement::Reussi)
            ->and($reservation->statut)->toBe(StatutReservation::Confirmee)
            ->and($reservation->code_reservation)->toMatch('/^[A-Z0-9]{6}$/');
    });

    it('rejoue checkout.completed sans rien changer ni regénérer le code', function () {
        $paiement = dwPaiement();
        dwEnvoyer(dwEvenement('checkout.completed', $paiement))->assertOk();
        $code = Reservation::query()->find($paiement->reservation_id)->code_reservation;

        dwEnvoyer(dwEvenement('checkout.completed', $paiement))->assertOk()->assertJsonPath('message', 'Déjà traité.');

        expect(Reservation::query()->find($paiement->reservation_id)->code_reservation)->toBe($code);
    });

    it('refuse un montant différent avec un 422 sans rien modifier', function () {
        $paiement = dwPaiement();

        dwEnvoyer(dwEvenement('checkout.completed', $paiement, 100))->assertUnprocessable();

        expect($paiement->refresh()->statut)->toBe(StatutPaiement::EnAttente)
            ->and(Reservation::query()->find($paiement->reservation_id)->statut)->toBe(StatutReservation::Acceptee);
    });

    it('tolère le facteur de montant', function () {
        config(['sopi.paiement.dexpay.amount_factor' => 100]);
        app()->forgetInstance(PaiementService::class);
        $paiement = dwPaiement();

        dwEnvoyer(dwEvenement('checkout.completed', $paiement, 450000))->assertOk();

        expect($paiement->refresh()->statut)->toBe(StatutPaiement::Reussi);
    });

    it('ignore une référence inconnue avec un 200', function () {
        dwEnvoyer(['event' => 'checkout.completed', 'reference' => 'SOPI-PAY-INCONNU', 'amount' => 4500, 'currency' => 'XOF'])->assertOk()->assertJsonPath('message', 'Événement ignoré.');
    });

    it('checkout.failed et checkout.cancelled passent un paiement en attente à ECHOUE', function (string $type) {
        $paiement = dwPaiement();

        dwEnvoyer(dwEvenement($type, $paiement))->assertOk();

        expect($paiement->refresh()->statut)->toBe(StatutPaiement::Echoue)
            ->and(Reservation::query()->find($paiement->reservation_id)->statut)->toBe(StatutReservation::Acceptee);
    })->with(['checkout.failed', 'checkout.cancelled']);

    it('checkout.initiated est ignoré avec un 200', function () {
        $paiement = dwPaiement();

        dwEnvoyer(dwEvenement('checkout.initiated', $paiement))->assertOk()->assertJsonPath('message', 'Événement ignoré.');

        expect($paiement->refresh()->statut)->toBe(StatutPaiement::EnAttente);
    });

    it('accepte completed avant initiated (ordre inversé) sans régression', function () {
        $paiement = dwPaiement();

        dwEnvoyer(dwEvenement('checkout.completed', $paiement))->assertOk();
        dwEnvoyer(dwEvenement('checkout.initiated', $paiement))->assertOk();

        expect($paiement->refresh()->statut)->toBe(StatutPaiement::Reussi);
    });

    it('un échec tardif ne touche pas un paiement réussi', function () {
        $paiement = dwPaiement();
        dwEnvoyer(dwEvenement('checkout.completed', $paiement))->assertOk();

        dwEnvoyer(dwEvenement('checkout.failed', $paiement))->assertOk()->assertJsonPath('message', 'Déjà traité.');

        expect($paiement->refresh()->statut)->toBe(StatutPaiement::Reussi);
    });

    it('checkout.refunded reçu avant checkout.completed ne confirme pas la réservation à tort', function () {
        $paiement = dwPaiement();

        dwEnvoyer(dwEvenement('checkout.refunded', $paiement))->assertOk();

        // Rien n'a été payé du point de vue de Sopi : le remboursement n'est pas appliqué, le paiement reste en attente.
        expect($paiement->refresh()->statut)->toBe(StatutPaiement::EnAttente);
    });

    it('ignore un événement inconnu avec un 200', function () {
        dwEnvoyer(['event' => 'truc.inconnu', 'reference' => 'X'])->assertOk()->assertJsonPath('message', 'Événement ignoré.');
    });

    it('répond 422 à un événement incomplet', function () {
        dwEnvoyer(['event' => 'checkout.completed', 'amount' => 4500])->assertUnprocessable();
    });
});

describe('signature', function () {
    it('répond 401 sans signature, avec une mauvaise clé, et ne modifie rien', function (?string $cle, bool $signer) {
        $paiement = dwPaiement();

        dwEnvoyer(dwEvenement('checkout.completed', $paiement), $cle, $signer)->assertUnauthorized();

        expect($paiement->refresh()->statut)->toBe(StatutPaiement::EnAttente);
    })->with([
        'sans signature' => [DW_CLE_SECRETE, false],
        'mauvaise clé' => ['autre-cle', true],
    ]);

    it('répond 503 quand le pilote DexPay n\'a pas de clés', function () {
        config(['sopi.paiement.dexpay.secret_key' => '', 'sopi.paiement.dexpay.public_key' => '']);
        app()->forgetInstance(PaiementService::class);
        Log::spy();

        dwEnvoyer(['event' => 'checkout.completed', 'reference' => 'X', 'amount' => 1, 'currency' => 'XOF'], '')->assertStatus(503);
    });
});

describe('événements de versement', function () {
    function dwRetraitEnCours(int $montant = 5000): Retrait
    {
        $prestataire = Prestataire::factory()->avoirSolde(0)->for(User::factory()->prestataire(), 'user')->create();

        return Retrait::factory()->enCours()->create(['prestataire_id' => $prestataire->id, 'montant' => $montant.'.00']);
    }

    it('payout.completed passe le retrait à REUSSI, une seule fois', function () {
        $retrait = dwRetraitEnCours();
        $evenement = ['event' => 'payout.completed', 'reference' => 'RTR-'.$retrait->id, 'metadata' => ['retrait_id' => $retrait->id]];

        dwEnvoyer($evenement)->assertOk()->assertJsonPath('message', 'Retrait réussi enregistré.');
        $traite = $retrait->refresh()->traite_le;
        dwEnvoyer($evenement)->assertOk()->assertJsonPath('message', 'Déjà traité.');

        expect($retrait->refresh()->statut)->toBe(StatutRetrait::Reussi)->and($retrait->traite_le->equalTo($traite))->toBeTrue()
            ->and($retrait->reference_dexpay)->toBe('RTR-'.$retrait->id)
            ->and($retrait->prestataire->fresh()->solde)->toBe('0.00');
    });

    it('payout.failed passe le retrait à ECHOUE et recrédite une seule fois, même rejoué', function (string $type) {
        $retrait = dwRetraitEnCours(5000);
        $evenement = ['event' => $type, 'reference' => 'RTR-'.$retrait->id];

        dwEnvoyer($evenement)->assertOk()->assertJsonPath('message', 'Échec du retrait enregistré.');
        dwEnvoyer($evenement)->assertOk()->assertJsonPath('message', 'Déjà traité.');
        dwEnvoyer($evenement)->assertOk();

        expect($retrait->refresh()->statut)->toBe(StatutRetrait::Echoue)->and($retrait->recredite_le)->not->toBeNull()
            ->and($retrait->prestataire->fresh()->solde)->toBe('5000.00');
    })->with(['payout.failed', 'payout.cancelled']);

    it('retrouve le retrait par la référence DexPay mémorisée', function () {
        $retrait = dwRetraitEnCours();
        $retrait->forceFill(['reference_dexpay' => 'PO-123456'])->save();

        dwEnvoyer(['event' => 'payout.completed', 'reference' => 'PO-123456'])->assertOk();

        expect($retrait->refresh()->statut)->toBe(StatutRetrait::Reussi);
    });

    it('ne repasse jamais un retrait réussi à ECHOUE ni ne recrédite', function () {
        $retrait = dwRetraitEnCours();
        dwEnvoyer(['event' => 'payout.completed', 'reference' => 'RTR-'.$retrait->id])->assertOk();

        dwEnvoyer(['event' => 'payout.failed', 'reference' => 'RTR-'.$retrait->id])->assertOk()->assertJsonPath('message', 'Déjà traité.');

        expect($retrait->refresh()->statut)->toBe(StatutRetrait::Reussi)->and($retrait->prestataire->fresh()->solde)->toBe('0.00');
    });

    it('signale par le journal un succès tardif sur un retrait déjà ECHOUE', function () {
        Log::spy();
        $retrait = dwRetraitEnCours();
        dwEnvoyer(['event' => 'payout.failed', 'reference' => 'RTR-'.$retrait->id])->assertOk();

        dwEnvoyer(['event' => 'payout.completed', 'reference' => 'RTR-'.$retrait->id])->assertOk();

        expect($retrait->refresh()->statut)->toBe(StatutRetrait::Echoue);
        Log::shouldHaveReceived('error')->once();
    });

    it('ignore un versement inconnu avec un 200', function () {
        dwEnvoyer(['event' => 'payout.completed', 'reference' => 'RTR-introuvable'])->assertOk()->assertJsonPath('message', 'Événement ignoré.');
    });
});
