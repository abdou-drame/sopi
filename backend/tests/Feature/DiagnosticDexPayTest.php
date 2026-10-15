<?php

use App\Enums\StatutReservation;
use App\Exceptions\ConfigurationPaiementInvalide;
use App\Exceptions\DexPayErreur;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\Retrait;
use App\Models\User;
use App\Services\Paiement\PaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

// Fausses clés volontairement reconnaissables : si l'une d'elles sort dans une réponse, le test échoue.
const DD_CLE_PUBLIQUE = 'pk_factice_DIAG_PUBLIQUE';
const DD_CLE_SECRETE = 'sk_factice_DIAG_SECRETE';
const DD_CLE_DANS_REPONSE = 'sk_factice_DIAG_RENVOYEE';
const DD_TELEPHONE = '771234567';
const DD_MESSAGE_503 = 'Le service de paiement est momentanément indisponible. Veuillez réessayer.';

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'sopi.paiement.driver' => 'dexpay',
        'sopi.paiement.dexpay.base_url' => 'https://dexpay.test/api/v1',
        'sopi.paiement.dexpay.public_key' => DD_CLE_PUBLIQUE,
        'sopi.paiement.dexpay.secret_key' => DD_CLE_SECRETE,
        'sopi.paiement.dexpay.amount_factor' => 1,
    ]);
    app()->forgetInstance(PaiementService::class);
});

function ddPayer(): TestResponse
{
    $client = User::factory()->create();
    test()->actingAs($client, 'sanctum');
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $prestation = Prestation::factory()->for($prestataire)->create(['prix' => '15000.00']);
    $debut = now()->addDay();
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
    $reservation = Reservation::factory()->create([
        'client_id' => $client->id, 'creneau_id' => $creneau->id, 'prestation_id' => $prestation->id,
        'statut' => StatutReservation::Acceptee, 'acceptee_le' => now(),
    ]);

    return test()->postJson('/api/v1/reservations/'.$reservation->id.'/payer');
}

function ddRetirer(): TestResponse
{
    $prestataire = Prestataire::factory()->avoirSolde(20000)->for(User::factory()->prestataire(), 'user')->create();
    test()->actingAs($prestataire->user, 'sanctum');

    return test()->postJson('/api/v1/prestataire/retraits', ['montant' => 5000, 'telephone_versement' => '77 123 45 67', 'operateur' => 'wave']);
}

function ddAucuneFuite(TestResponse $reponse): void
{
    $brut = $reponse->getContent();
    foreach ([DD_CLE_PUBLIQUE, DD_CLE_SECRETE, DD_CLE_DANS_REPONSE, DD_TELEPHONE, 'pk_factice', 'sk_factice', 'x-api'] as $interdit) {
        expect(str_contains($brut, $interdit))->toBeFalse("« $interdit » trouvé dans la réponse");
    }
}

function ddRefusDexPay(): void
{
    Http::fake(['dexpay.test/*' => Http::response(['message' => 'Acces refuse '.DD_CLE_DANS_REPONSE.' pour +221 77 123 45 67', 'api_key' => DD_CLE_PUBLIQUE, 'secret' => DD_CLE_SECRETE], 401)]);
}

describe('réglage coupé (par défaut)', function () {
    it('est coupé par défaut', function () {
        expect(config('sopi.paiement.dexpay.diagnostic_reponse'))->toBeFalse();
    });

    it('ne change pas la réponse 503 du paiement : aucun champ diagnostic, aucune fuite', function () {
        ddRefusDexPay();

        $reponse = ddPayer()->assertStatus(503)->assertExactJson(['message' => DD_MESSAGE_503]);

        ddAucuneFuite($reponse);
    });

    it('ne change pas la réponse 503 du retrait', function () {
        ddRefusDexPay();

        $reponse = ddRetirer()->assertStatus(503);

        expect($reponse->json())->toBe(['message' => 'Le versement n\'a pas pu être effectué. Votre solde n\'a pas été débité. Veuillez réessayer.']);
        ddAucuneFuite($reponse);
    });
});

describe('réglage actif', function () {
    beforeEach(fn () => config(['sopi.paiement.dexpay.diagnostic_reponse' => true]));

    it('ajoute le diagnostic au 503 du paiement : événement, exception, code HTTP, extrait masqué, sans clé ni numéro', function () {
        ddRefusDexPay();

        $reponse = ddPayer()->assertStatus(503)->assertJsonPath('message', DD_MESSAGE_503);

        $diagnostic = $reponse->json('diagnostic');
        expect(array_keys($diagnostic))->toBe(['evenement', 'exception', 'code_http', 'extrait'])
            ->and($diagnostic['evenement'])->toBe('dexpay.creer_session_echec')
            ->and($diagnostic['exception'])->toBe(DexPayErreur::class)
            ->and($diagnostic['code_http'])->toBe(401)
            ->and($diagnostic['extrait'])->toContain('Acces refuse')
            ->and(mb_strlen($diagnostic['extrait']))->toBeLessThanOrEqual(300);
        ddAucuneFuite($reponse);
    });

    it('borne l\'extrait à 300 caractères', function () {
        Http::fake(['dexpay.test/*' => Http::response(str_repeat('erreur ', 300), 500)]);

        $diagnostic = ddPayer()->assertStatus(503)->json('diagnostic');

        expect(mb_strlen($diagnostic['extrait']))->toBeLessThanOrEqual(300);
    });

    it('donne les NOMS des variables manquantes, jamais leurs valeurs', function () {
        config(['sopi.paiement.dexpay.secret_key' => '', 'sopi.paiement.dexpay.public_key' => '']);
        app()->forgetInstance(PaiementService::class);

        $reponse = ddPayer()->assertStatus(503);

        $diagnostic = $reponse->json('diagnostic');
        expect($diagnostic['exception'])->toBe(ConfigurationPaiementInvalide::class)
            ->and($diagnostic['variables_manquantes'])->toBe(['DEXPAY_PUBLIC_KEY', 'DEXPAY_SECRET_KEY'])
            ->and($diagnostic)->not->toHaveKey('code_http');
        ddAucuneFuite($reponse);
    });

    it('donne la classe d\'exception quand DexPay est injoignable, sans code HTTP', function () {
        Http::fake(['dexpay.test/*' => fn () => throw new ConnectionException('cURL error 6: Could not resolve host dexpay.test')]);

        $reponse = ddPayer()->assertStatus(503);

        $diagnostic = $reponse->json('diagnostic');
        expect($diagnostic['exception'])->toBe(DexPayErreur::class)
            ->and($diagnostic)->not->toHaveKey('code_http')
            ->and($diagnostic['extrait'])->toContain('Could not resolve host');
        ddAucuneFuite($reponse);
    });

    it('ajoute le diagnostic au 503 du retrait, sans clé ni numéro', function () {
        ddRefusDexPay();

        $reponse = ddRetirer()->assertStatus(503);

        $diagnostic = $reponse->json('diagnostic');
        expect($diagnostic['evenement'])->toBe('dexpay.versement_echec')->and($diagnostic['code_http'])->toBe(401);
        expect(Retrait::query()->count())->toBe(1);
        ddAucuneFuite($reponse);
    });

    it('n\'ajoute rien aux réponses qui ne viennent pas de DexPay (paiement réussi)', function () {
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'success', 'data' => ['reference' => 'SOPI-PAY-ok', 'payment_url' => 'https://pay.dexpay.test/abc']], 201)]);

        $reponse = ddPayer()->assertSuccessful();

        expect($reponse->json())->not->toHaveKey('diagnostic');
    });
});
