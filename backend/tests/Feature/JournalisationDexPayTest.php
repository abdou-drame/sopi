<?php

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Exceptions\ConfigurationPaiementInvalide;
use App\Exceptions\DexPayErreur;
use App\Models\Creneau;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\Retrait;
use App\Models\User;
use App\Services\Paiement\PaiementService;
use App\Services\RemboursementPaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

// Fausses clés volontairement reconnaissables : si l'une d'elles apparaît dans un journal, le test échoue.
const JD_CLE_PUBLIQUE = 'pk_factice_JOURNAL_PUBLIQUE';
const JD_CLE_SECRETE = 'sk_factice_JOURNAL_SECRETE';
const JD_CLE_DANS_REPONSE = 'sk_factice_RENVOYEE_PAR_DEXPAY';
const JD_TELEPHONE = '771234567';

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'sopi.paiement.driver' => 'dexpay',
        'sopi.paiement.dexpay.base_url' => 'https://dexpay.test/api/v1',
        'sopi.paiement.dexpay.public_key' => JD_CLE_PUBLIQUE,
        'sopi.paiement.dexpay.secret_key' => JD_CLE_SECRETE,
        'sopi.paiement.dexpay.amount_factor' => 1,
        'sopi.paiement.webhook_secret' => JD_CLE_SECRETE,
    ]);
    app()->forgetInstance(PaiementService::class);
});

/**
 * Capture toutes les lignes de journal émises pendant le test.
 *
 * @return ArrayObject<int, array{niveau: string, message: string, contexte: array<string, mixed>}>
 */
function jdCapturer(): ArrayObject
{
    $lignes = new ArrayObject;
    Log::listen(function ($ligne) use ($lignes) {
        $lignes->append(['niveau' => $ligne->level, 'message' => (string) $ligne->message, 'contexte' => $ligne->context]);
    });

    return $lignes;
}

/** @return list<array{niveau: string, message: string, contexte: array<string, mixed>}> */
function jdLignes(ArrayObject $lignes, string $evenement): array
{
    return array_values(array_filter($lignes->getArrayCopy(), fn (array $l) => ($l['contexte']['evenement'] ?? null) === $evenement));
}

/** Aucune fausse clé, signature ni numéro dans AUCUNE ligne (message ou contexte). */
function jdAucunSecret(ArrayObject $lignes): void
{
    $tout = json_encode($lignes->getArrayCopy(), JSON_UNESCAPED_UNICODE);
    foreach ([JD_CLE_PUBLIQUE, JD_CLE_SECRETE, JD_CLE_DANS_REPONSE, JD_TELEPHONE, 'pk_factice', 'sk_factice'] as $interdit) {
        expect(str_contains($tout, $interdit))->toBeFalse("« $interdit » trouvé dans le journal");
    }
}

function jdReservationAcceptee(User $client): Reservation
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
    $prestation = Prestation::factory()->for($prestataire)->create(['prix' => '15000.00']);
    $debut = now()->addDay();
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);

    return Reservation::factory()->create([
        'client_id' => $client->id,
        'creneau_id' => $creneau->id,
        'prestation_id' => $prestation->id,
        'statut' => StatutReservation::Acceptee,
        'acceptee_le' => now(),
    ]);
}

function jdPayer(): TestResponse
{
    $client = User::factory()->create();
    test()->actingAs($client, 'sanctum');
    $reservation = jdReservationAcceptee($client);

    return test()->postJson('/api/v1/reservations/'.$reservation->id.'/payer');
}

describe('création de session de paiement', function () {
    it('journalise l\'échec en ERROR avec le code HTTP et un extrait masqué, sans changer la réponse de l\'API', function () {
        $lignes = jdCapturer();
        Http::fake(['dexpay.test/*' => Http::response(['message' => 'Acces refuse '.JD_CLE_DANS_REPONSE, 'api_key' => JD_CLE_PUBLIQUE, 'secret' => JD_CLE_SECRETE], 401)]);

        jdPayer()->assertStatus(503)->assertJsonPath('message', 'Le service de paiement est momentanément indisponible. Veuillez réessayer.');

        $paiement = Paiement::query()->firstOrFail();
        $trouvees = jdLignes($lignes, 'dexpay.creer_session_echec');
        expect($trouvees)->toHaveCount(1);
        $ligne = $trouvees[0];
        expect($ligne['niveau'])->toBe('error')
            ->and($ligne['contexte']['paiement_id'])->toBe($paiement->id)
            ->and($ligne['contexte']['exception'])->toBe(DexPayErreur::class)
            ->and($ligne['contexte']['code_http'])->toBe(401)
            ->and($ligne['contexte']['extrait'])->toContain('Acces refuse')
            ->and(mb_strlen($ligne['contexte']['extrait']))->toBeLessThanOrEqual(300);
        jdAucunSecret($lignes);
    });

    it('borne l\'extrait à 300 caractères et masque les longues chaînes opaques', function () {
        $lignes = jdCapturer();
        $opaque = str_repeat('a1B2c3D4', 8);
        Http::fake(['dexpay.test/*' => Http::response('<html>'.str_repeat('erreur ', 200).$opaque.'</html>', 500)]);

        jdPayer()->assertStatus(503);

        $extrait = jdLignes($lignes, 'dexpay.creer_session_echec')[0]['contexte']['extrait'];
        expect(mb_strlen($extrait))->toBeLessThanOrEqual(300)->and($extrait)->not->toContain($opaque);
        jdAucunSecret($lignes);
    });

    it('masque une clé et un numéro de téléphone présents dans la réponse', function () {
        $lignes = jdCapturer();
        Http::fake(['dexpay.test/*' => Http::response(['message' => 'Numéro +221 77 123 45 67 invalide, jeton Bearer abcdef123456 clé '.JD_CLE_DANS_REPONSE], 422)]);

        jdPayer()->assertStatus(503);

        $extrait = jdLignes($lignes, 'dexpay.creer_session_echec')[0]['contexte']['extrait'];
        expect($extrait)->toContain('[numéro masqué]')->and($extrait)->not->toContain('abcdef123456');
        jdAucunSecret($lignes);
    });

    it('journalise un service injoignable avec la classe de l\'exception et sans code HTTP', function () {
        $lignes = jdCapturer();
        Http::fake(['dexpay.test/*' => fn () => throw new ConnectionException('cURL error 28: délai dépassé')]);

        jdPayer()->assertStatus(503);

        $ligne = jdLignes($lignes, 'dexpay.creer_session_echec')[0];
        expect($ligne['niveau'])->toBe('error')
            ->and($ligne['contexte']['exception'])->toBe(DexPayErreur::class)
            ->and($ligne['contexte'])->not->toHaveKey('code_http')
            ->and($ligne['contexte']['extrait'])->toContain('délai dépassé');
        jdAucunSecret($lignes);
    });

    it('journalise une réponse de succès sans lien de paiement', function () {
        $lignes = jdCapturer();
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'success', 'data' => ['reference' => 'x']], 201)]);

        jdPayer()->assertStatus(503);

        expect(jdLignes($lignes, 'dexpay.creer_session_echec'))->toHaveCount(1);
        jdAucunSecret($lignes);
    });

    it('journalise les NOMS des variables manquantes, jamais leurs valeurs', function () {
        $lignes = jdCapturer();
        config(['sopi.paiement.dexpay.secret_key' => '', 'sopi.paiement.dexpay.base_url' => '']);
        app()->forgetInstance(PaiementService::class);

        jdPayer()->assertStatus(503)->assertJsonPath('message', 'Le service de paiement est momentanément indisponible. Veuillez réessayer.');

        $ligne = jdLignes($lignes, 'dexpay.creer_session_echec')[0];
        expect($ligne['niveau'])->toBe('error')
            ->and($ligne['contexte']['exception'])->toBe(ConfigurationPaiementInvalide::class)
            ->and($ligne['contexte']['variables_manquantes'])->toBe(['DEXPAY_BASE_URL', 'DEXPAY_SECRET_KEY']);
        jdAucunSecret($lignes);
    });
});

describe('remboursement', function () {
    it('journalise un refus de DexPay avec le code HTTP et l\'extrait masqué', function () {
        $lignes = jdCapturer();
        Http::fake(['dexpay.test/*' => Http::response(['message' => 'refus '.JD_CLE_DANS_REPONSE], 403)]);
        $paiement = Paiement::factory()->reussi()->create();

        expect(app(RemboursementPaiementService::class)->rembourser($paiement))->toBeFalse();

        $ligne = jdLignes($lignes, 'dexpay.remboursement_echec')[0];
        expect($ligne['niveau'])->toBe('error')
            ->and($ligne['contexte']['paiement_id'])->toBe($paiement->id)
            ->and($ligne['contexte']['code_http'])->toBe(403)
            ->and($ligne['contexte']['extrait'])->toContain('refus')
            ->and(mb_strlen($ligne['contexte']['extrait']))->toBeLessThanOrEqual(300);
        jdAucunSecret($lignes);
    });

    it('journalise un service injoignable', function () {
        $lignes = jdCapturer();
        Http::fake(['dexpay.test/*' => fn () => throw new ConnectionException('délai dépassé')]);
        $paiement = Paiement::factory()->reussi()->create();

        expect(app(RemboursementPaiementService::class)->rembourser($paiement))->toBeFalse();

        $ligne = jdLignes($lignes, 'dexpay.remboursement_echec')[0];
        expect($ligne['niveau'])->toBe('error')->and($ligne['contexte']['paiement_id'])->toBe($paiement->id);
        jdAucunSecret($lignes);
    });

    it('journalise un HTTP 200 au statut « failed »', function () {
        $lignes = jdCapturer();
        Http::fake(['dexpay.test/*' => Http::response(['status' => 'failed'], 200)]);
        $paiement = Paiement::factory()->reussi()->create();

        app(RemboursementPaiementService::class)->rembourser($paiement);

        $ligne = jdLignes($lignes, 'dexpay.remboursement_echec')[0];
        expect($ligne['contexte']['motif'])->toBe('statut_failed')->and($ligne['contexte']['code_http'])->toBe(200);
        jdAucunSecret($lignes);
    });
});

describe('versement (retrait)', function () {
    function jdDemanderRetrait(): TestResponse
    {
        $prestataire = Prestataire::factory()->avoirSolde(20000)->for(User::factory()->prestataire(), 'user')->create();
        test()->actingAs($prestataire->user, 'sanctum');

        return test()->postJson('/api/v1/prestataire/retraits', ['montant' => 5000, 'telephone_versement' => '77 123 45 67', 'operateur' => 'wave']);
    }

    it('journalise un refus certain avec le motif, le code HTTP et l\'extrait', function () {
        $lignes = jdCapturer();
        Http::fake(['dexpay.test/*' => Http::response(['message' => 'refus '.JD_CLE_DANS_REPONSE.' 77 123 45 67'], 422)]);

        jdDemanderRetrait()->assertStatus(503);

        $retrait = Retrait::query()->firstOrFail();
        $ligne = jdLignes($lignes, 'dexpay.versement_echec')[0];
        expect($ligne['niveau'])->toBe('error')
            ->and($ligne['contexte']['retrait_id'])->toBe($retrait->id)
            ->and($ligne['contexte']['motif'])->toBe('http_422')
            ->and($ligne['contexte']['code_http'])->toBe(422)
            ->and(mb_strlen($ligne['contexte']['extrait']))->toBeLessThanOrEqual(300);
        jdAucunSecret($lignes);
    });

    it('journalise un résultat incertain avec la classe de l\'exception et le code HTTP', function () {
        $lignes = jdCapturer();
        Http::fake(['dexpay.test/*' => Http::response(['message' => 'oups '.JD_CLE_DANS_REPONSE], 500)]);

        jdDemanderRetrait()->assertCreated();

        $retrait = Retrait::query()->firstOrFail();
        $ligne = jdLignes($lignes, 'dexpay.versement_echec')[0];
        expect($ligne['niveau'])->toBe('error')
            ->and($ligne['contexte']['retrait_id'])->toBe($retrait->id)
            ->and($ligne['contexte']['exception'])->toBe(DexPayErreur::class)
            ->and($ligne['contexte']['code_http'])->toBe(500);
        jdAucunSecret($lignes);
    });

    it('journalise un service injoignable', function () {
        $lignes = jdCapturer();
        Http::fake(['dexpay.test/*' => fn () => throw new ConnectionException('délai dépassé')]);

        jdDemanderRetrait()->assertCreated();

        expect(jdLignes($lignes, 'dexpay.versement_echec'))->toHaveCount(1);
        jdAucunSecret($lignes);
    });
});

describe('webhook', function () {
    function jdWebhook(array $evenement, string $cle = JD_CLE_SECRETE): TestResponse
    {
        $corps = json_encode($evenement);

        return test()->call('POST', '/api/v1/webhooks/paiement', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $corps, $cle),
        ], $corps);
    }

    it('journalise un rejet de signature en WARNING, sans le corps ni la signature', function () {
        $lignes = jdCapturer();

        jdWebhook(['event' => 'checkout.completed', 'reference' => 'SOPI-PAY-secret-corps-'.JD_TELEPHONE, 'amount' => 4500], 'autre_cle')->assertStatus(401);

        $trouvees = jdLignes($lignes, 'webhook.signature_rejetee');
        expect($trouvees)->toHaveCount(1)->and($trouvees[0]['niveau'])->toBe('warning');
        expect(json_encode($trouvees))->not->toContain('secret-corps')->and(json_encode($trouvees))->not->toContain(hash_hmac('sha256', '', 'autre_cle'));
        jdAucunSecret($lignes);
    });

    it('journalise chaque événement reçu en INFO avec le seul type et l\'identifiant de paiement', function () {
        $lignes = jdCapturer();
        $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create();
        $prestation = Prestation::factory()->for($prestataire)->create(['prix' => '15000.00']);
        $debut = now()->addDays(2);
        $creneau = Creneau::factory()->for($prestataire)->reserve()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
        $reservation = Reservation::factory()->acceptee()->create(['client_id' => User::factory()->create()->id, 'creneau_id' => $creneau->id, 'prestation_id' => $prestation->id]);
        $paiement = Paiement::factory()->enAttente()->create(['reservation_id' => $reservation->id, 'montant_acompte' => '4500.00', 'reference_dexpay' => 'SOPI-PAY-'.fake()->uuid()]);

        jdWebhook(['event' => 'checkout.completed', 'reference' => $paiement->reference_dexpay, 'amount' => 4500, 'currency' => 'XOF', 'customer' => ['phone' => JD_TELEPHONE]])->assertOk();

        $ligne = jdLignes($lignes, 'webhook.evenement_recu')[0];
        expect($ligne['niveau'])->toBe('info')
            ->and($ligne['contexte']['type'])->toBe('checkout.completed')
            ->and($ligne['contexte']['paiement'])->toBe($paiement->reference_dexpay)
            ->and(array_keys($ligne['contexte']))->toBe(['evenement', 'type', 'paiement']);
        jdAucunSecret($lignes);
    });

    it('journalise aussi un événement ignoré, avec son type', function () {
        $lignes = jdCapturer();

        jdWebhook(['event' => 'checkout.created', 'reference' => 'SOPI-PAY-inconnu'])->assertOk();

        expect(jdLignes($lignes, 'webhook.evenement_recu')[0]['contexte']['type'])->toBe('checkout.created');
    });

    it('journalise en ERROR les noms des variables manquantes quand la configuration est incomplète', function () {
        $lignes = jdCapturer();
        config(['sopi.paiement.dexpay.public_key' => '']);
        app()->forgetInstance(PaiementService::class);

        jdWebhook(['event' => 'checkout.completed', 'reference' => 'x', 'amount' => 1])->assertStatus(503);

        $ligne = jdLignes($lignes, 'webhook.configuration_invalide')[0];
        expect($ligne['niveau'])->toBe('error')->and($ligne['contexte']['variables_manquantes'])->toBe(['DEXPAY_PUBLIC_KEY']);
        jdAucunSecret($lignes);
    });
});

it('ne journalise aucun secret sur un paiement réussi de bout en bout', function () {
    $lignes = jdCapturer();
    Http::fake(['dexpay.test/*' => Http::response(['status' => 'success', 'data' => ['reference' => 'SOPI-PAY-ok', 'payment_url' => 'https://pay.dexpay.test/abc']], 201)]);

    jdPayer()->assertSuccessful();

    expect(jdLignes($lignes, 'dexpay.creer_session_echec'))->toBeEmpty();
    expect(Paiement::query()->firstOrFail()->statut)->toBe(StatutPaiement::EnAttente);
    jdAucunSecret($lignes);
});
