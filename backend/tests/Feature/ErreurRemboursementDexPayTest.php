<?php

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Services\Paiement\PaiementService;
use App\Services\RemboursementPaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const ER_PUBLIQUE = 'pk_factice_ERR_PUBLIQUE';
const ER_SECRETE = 'sk_factice_ERR_SECRETE';

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'sopi.paiement.driver' => 'dexpay',
        'sopi.paiement.dexpay.base_url' => 'https://dexpay.test/api/v1',
        'sopi.paiement.dexpay.public_key' => ER_PUBLIQUE,
        'sopi.paiement.dexpay.secret_key' => ER_SECRETE,
    ]);
    app()->forgetInstance(PaiementService::class);
});

function erTenter(Paiement $paiement): ?string
{
    app(RemboursementPaiementService::class)->rembourser($paiement);

    return Paiement::query()->find($paiement->id)->remboursement_derniere_erreur;
}

function erSansFuite(?string $erreur): void
{
    expect($erreur)->not->toBeNull()
        ->and(mb_strlen($erreur))->toBeLessThanOrEqual(300);
    foreach ([ER_PUBLIQUE, ER_SECRETE, 'pk_factice', 'sk_factice', '771234567'] as $interdit) {
        expect(str_contains($erreur, $interdit))->toBeFalse("« $interdit » trouvé dans l'erreur enregistrée");
    }
}

it('enregistre le code HTTP et l\'extrait pour 400, 401, 404 et 500', function (int $code) {
    Http::fake(['dexpay.test/*' => Http::response(['message' => 'Solde insuffisant', 'cle' => ER_SECRETE, 'tel' => '+221 77 123 45 67'], $code)]);
    $paiement = Paiement::factory()->reussi()->create();

    $erreur = erTenter($paiement);

    expect($erreur)->toStartWith("HTTP $code (http_$code)")->toContain('Solde insuffisant');
    erSansFuite($erreur);
    expect($paiement->refresh()->statut)->toBe(StatutPaiement::Reussi);
})->with([400, 401, 404, 500]);

it('enregistre un refus renvoyé en HTTP 200 avec le statut failed', function () {
    Http::fake(['dexpay.test/*' => Http::response(['status' => 'failed', 'message' => 'Already refunded'], 200)]);
    $paiement = Paiement::factory()->reussi()->create();

    $erreur = erTenter($paiement);

    expect($erreur)->toStartWith('HTTP 200 (statut_failed)')->toContain('Already refunded');
    erSansFuite($erreur);
});

it('enregistre un délai dépassé sans code HTTP', function () {
    Http::fake(['dexpay.test/*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);
    $paiement = Paiement::factory()->reussi()->create();

    $erreur = erTenter($paiement);

    expect($erreur)->toStartWith('injoignable')->toContain('timed out');
    erSansFuite($erreur);
});

it('borne l\'erreur enregistrée à 300 caractères', function () {
    Http::fake(['dexpay.test/*' => Http::response(str_repeat('erreur ', 300), 500)]);

    erSansFuite(erTenter(Paiement::factory()->reussi()->create()));
});

it('remplace l\'erreur à chaque échec et la vide quand le remboursement réussit', function () {
    Http::fake(['dexpay.test/*' => Http::sequence()
        ->push(['message' => 'premier'], 400)
        ->push(['message' => 'second'], 500)
        ->push(['status' => 'success'], 200)]);
    $paiement = Paiement::factory()->reussi()->create();

    expect(erTenter($paiement))->toContain('premier');

    expect(erTenter($paiement))->toContain('second')->not->toContain('premier');

    expect(erTenter($paiement))->toBeNull()
        ->and($paiement->refresh()->statut)->toBe(StatutPaiement::Rembourse);
});

it('expose remboursementDerniereErreur dans la liste et le détail admin, vide par défaut', function () {
    adminConnecte();
    $vide = Paiement::factory()->reussi()->create();
    $echoue = Paiement::factory()->reussi()->create(['remboursement_du_le' => now(), 'remboursement_derniere_erreur' => 'HTTP 400 (http_400) : Solde insuffisant']);

    $liste = collect($this->getJson('/api/v1/admin/paiements')->assertOk()->json('data'))->keyBy('id');
    expect($liste[$vide->id]['remboursementDerniereErreur'])->toBeNull()
        ->and($liste[$echoue->id]['remboursementDerniereErreur'])->toBe('HTTP 400 (http_400) : Solde insuffisant');

    $this->getJson('/api/v1/admin/paiements/'.$echoue->id)->assertOk()
        ->assertJsonPath('remboursementDerniereErreur', 'HTTP 400 (http_400) : Solde insuffisant');
});
