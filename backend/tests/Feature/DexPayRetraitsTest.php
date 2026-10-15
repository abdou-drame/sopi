<?php

use App\Enums\OperateurRetrait;
use App\Enums\StatutRetrait;
use App\Models\Prestataire;
use App\Models\Retrait;
use App\Models\User;
use App\Services\Paiement\PaiementService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const DR_CLE_PUBLIQUE = 'pk_factice_retrait';
const DR_CLE_SECRETE = 'sk_factice_retrait';

beforeEach(function () {
    config([
        'sopi.paiement.driver' => 'dexpay',
        'sopi.paiement.dexpay.base_url' => 'https://dexpay.test/api/v1',
        'sopi.paiement.dexpay.public_key' => DR_CLE_PUBLIQUE,
        'sopi.paiement.dexpay.secret_key' => DR_CLE_SECRETE,
        'sopi.paiement.dexpay.payout_operator_fields' => 'operator',
        'sopi.paiement.dexpay.amount_factor' => 1,
    ]);
    app()->forgetInstance(PaiementService::class);
    Http::preventStrayRequests();
});

function drPrestataire(int $solde = 20000): Prestataire
{
    $prestataire = Prestataire::factory()->avoirSolde($solde)->for(User::factory()->prestataire(), 'user')->create();
    test()->actingAs($prestataire->user, 'sanctum');

    return $prestataire;
}

function drDemander(array $donnees = []): TestResponse
{
    return test()->postJson('/api/v1/prestataire/retraits', array_merge(['montant' => 5000, 'telephone_versement' => '77 123 45 67', 'operateur' => 'wave'], $donnees));
}

describe('validation de l\'opérateur', function () {
    it('exige un opérateur valide, avec des messages en français, sans débiter', function (?string $operateur, string $message) {
        $prestataire = drPrestataire();
        Http::fake();

        drDemander(['operateur' => $operateur])->assertUnprocessable()->assertJsonPath('errors.operateur.0', $message);

        expect($prestataire->fresh()->solde)->toBe('20000.00')->and(Retrait::query()->count())->toBe(0);
        Http::assertNothingSent();
    })->with([
        'absent' => [null, 'L\'opérateur du versement est obligatoire (wave ou orange_money).'],
        'inconnu' => ['free_money', 'L\'opérateur doit être wave ou orange_money.'],
    ]);

    it('normalise le numéro en +221… avant l\'envoi et enregistre l\'opérateur', function () {
        drPrestataire();
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'COMPLETED', 'reference' => 'RTR-x'], 201)]);

        $reponse = drDemander(['operateur' => 'orange_money'])->assertCreated();

        $retrait = Retrait::query()->findOrFail($reponse->json('id'));
        expect($retrait->operateur)->toBe(OperateurRetrait::OrangeMoney)->and($retrait->telephone_versement)->toBe('+221771234567');
        Http::assertSent(fn (Request $r) => $r['destination_phone'] === '+221771234567' && $r['destination_details']['operator'] === 'orange_money_sn_payout');
    });
});

describe('versement terminé immédiatement', function () {
    it('envoie la bonne requête et passe le retrait à REUSSI', function () {
        $prestataire = drPrestataire();
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'success', 'data' => ['status' => 'COMPLETED', 'reference' => 'PO-777']], 201)]);

        $reponse = drDemander()->assertCreated()->assertJsonPath('statut', 'reussi');

        $retrait = Retrait::query()->findOrFail($reponse->json('id'));
        expect($retrait->statut)->toBe(StatutRetrait::Reussi)->and($retrait->reference_dexpay)->toBe('PO-777')->and($prestataire->fresh()->solde)->toBe('15000.00');
        Http::assertSent(function (Request $r) use ($retrait) {
            $corps = $r->data();

            return $r->url() === 'https://dexpay.test/api/v1/payouts'
                && $r->header('x-api-key') === [DR_CLE_PUBLIQUE]
                && $r->header('x-api-secret') === [DR_CLE_SECRETE]
                && $r->header('Idempotency-Key') === [$retrait->id]
                && $corps['reference'] === 'RTR-'.$retrait->id
                && strlen($corps['reference']) <= 64
                && $corps['amount'] === 5000
                && $corps['currency'] === 'XOF'
                && $corps['destination_details'] === ['operator' => 'wave_sn_payout', 'countryISO' => 'SN']
                && $corps['webhook_url'] === rtrim(config('app.url'), '/').'/api/v1/webhooks/paiement'
                && $corps['metadata'] === ['retrait_id' => $retrait->id]
                && ! str_contains($r->body(), DR_CLE_SECRETE);
        });
    });

    it('peut envoyer plusieurs champs d\'opérateur si la configuration le demande', function () {
        config(['sopi.paiement.dexpay.payout_operator_fields' => 'operator,provider_short_name']);
        drPrestataire();
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'COMPLETED'], 201)]);

        drDemander()->assertCreated();

        Http::assertSent(fn (Request $r) => $r['destination_details']['operator'] === 'wave_sn_payout' && $r['destination_details']['provider_short_name'] === 'wave_sn_payout');
    });
});

describe('versement accepté mais pas terminé', function () {
    it('laisse le retrait EN_COURS, débité, avec la référence DexPay, puis le webhook conclut', function (string $statut) {
        $prestataire = drPrestataire();
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'success', 'data' => ['status' => $statut, 'reference' => 'PO-888']], 201)]);

        $reponse = drDemander()->assertCreated()->assertJsonPath('statut', 'en_cours');

        $retrait = Retrait::query()->findOrFail($reponse->json('id'));
        expect($retrait->statut)->toBe(StatutRetrait::EnCours)->and($retrait->reference_dexpay)->toBe('PO-888')->and($prestataire->fresh()->solde)->toBe('15000.00');

        $corps = json_encode(['event' => 'payout.completed', 'reference' => 'PO-888', 'metadata' => ['retrait_id' => $retrait->id]]);
        $this->call('POST', '/api/v1/webhooks/paiement', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $corps, DR_CLE_SECRETE),
        ], $corps)->assertOk();

        expect($retrait->refresh()->statut)->toBe(StatutRetrait::Reussi);
    })->with(['PENDING', 'PROCESSING', 'pending']);
});

describe('refus certain', function () {
    it('passe le retrait à ECHOUE, recrédite une seule fois, répond 503 et alerte sans fuite', function (int $code) {
        $prestataire = drPrestataire();
        Log::spy();
        Http::fake(['dexpay.test/*' => Http::response(['message' => 'refus '.DR_CLE_SECRETE], $code)]);

        drDemander()->assertStatus(503);

        $retrait = Retrait::query()->firstOrFail();
        expect($retrait->statut)->toBe(StatutRetrait::Echoue)->and($retrait->recredite_le)->not->toBeNull()->and($prestataire->fresh()->solde)->toBe('20000.00');
        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $contexte = []) => ! str_contains(json_encode($contexte), DR_CLE_SECRETE) && ! str_contains(json_encode($contexte), '771234567'))->atLeast()->once();
    })->with([400, 401, 422, 503]);
});

describe('résultat ambigu', function () {
    it('laisse le retrait EN_COURS, débité, sans recrédit, pour 409, 500 et réponse illisible', function (int $code, mixed $corps) {
        $prestataire = drPrestataire();
        Http::fake(['dexpay.test/*' => Http::response($corps, $code)]);

        drDemander()->assertCreated()->assertJsonPath('statut', 'en_cours');

        $retrait = Retrait::query()->firstOrFail();
        expect($retrait->statut)->toBe(StatutRetrait::EnCours)->and($retrait->recredite_le)->toBeNull()->and($prestataire->fresh()->solde)->toBe('15000.00');
    })->with([
        'référence déjà utilisée' => [409, ['code' => 'REFERENCE_ALREADY_USED']],
        'erreur serveur' => [500, ['message' => 'oups']],
        'réponse illisible' => [201, 'pas du json'],
        'statut inconnu' => [201, ['status' => 'MYSTERE']],
    ]);

    it('laisse le retrait EN_COURS sur un délai dépassé', function () {
        $prestataire = drPrestataire();
        Http::fake(['dexpay.test/*' => fn () => throw new ConnectionException('délai')]);

        drDemander()->assertCreated()->assertJsonPath('statut', 'en_cours');

        expect(Retrait::query()->firstOrFail()->statut)->toBe(StatutRetrait::EnCours)->and($prestataire->fresh()->solde)->toBe('15000.00');
    });
});

describe('réconciliation', function () {
    function drRetraitAncien(int $minutes = 30): Retrait
    {
        $prestataire = Prestataire::factory()->avoirSolde(0)->for(User::factory()->prestataire(), 'user')->create();

        return Retrait::factory()->enCours()->create(['prestataire_id' => $prestataire->id, 'montant' => '5000.00', 'created_at' => now()->subMinutes($minutes)]);
    }

    it('applique « completed » et « failed » (un seul recrédit) aux retraits bloqués', function () {
        $reussi = drRetraitAncien();
        $echoue = drRetraitAncien();
        Http::fake([
            'dexpay.test/api/v1/payouts/RTR-'.$reussi->id => Http::response(['status' => 'success', 'data' => ['status' => 'COMPLETED']], 200),
            'dexpay.test/api/v1/payouts/RTR-'.$echoue->id => Http::response(['data' => ['status' => 'FAILED']], 200),
        ]);

        Artisan::call('sopi:retraits:reconcilier');
        Artisan::call('sopi:retraits:reconcilier');

        expect($reussi->refresh()->statut)->toBe(StatutRetrait::Reussi)
            ->and($echoue->refresh()->statut)->toBe(StatutRetrait::Echoue)->and($echoue->prestataire->fresh()->solde)->toBe('5000.00');
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->header('x-api-secret') === [DR_CLE_SECRETE]);
    });

    it('ne change rien si DexPay répond encore « pending », en erreur ou 404', function (int $code, array $corps) {
        $retrait = drRetraitAncien();
        Http::fake(['dexpay.test/*' => Http::response($corps, $code)]);

        Artisan::call('sopi:retraits:reconcilier');

        expect($retrait->refresh()->statut)->toBe(StatutRetrait::EnCours)->and($retrait->prestataire->fresh()->solde)->toBe('0.00');
    })->with([
        'en attente' => [200, ['data' => ['status' => 'PENDING']]],
        'introuvable' => [404, ['message' => 'non trouvé']],
        'erreur serveur' => [500, []],
    ]);

    it('ignore les retraits récents et ceux déjà terminés', function () {
        drRetraitAncien(2);
        Retrait::factory()->reussi()->create(['created_at' => now()->subHour()]);
        Http::fake();

        Artisan::call('sopi:retraits:reconcilier');

        Http::assertNothingSent();
    });

    it('est planifiée toutes les dix minutes', function () {
        $evenements = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command, 'sopi:retraits:reconcilier'));

        expect($evenements)->toHaveCount(1)->and($evenements->first()->expression)->toBe('*/10 * * * *');
    });
});
