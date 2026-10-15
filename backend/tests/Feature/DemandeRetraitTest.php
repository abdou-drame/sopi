<?php

use App\Enums\OperateurRetrait;
use App\Enums\StatutRetrait;
use App\Models\Prestataire;
use App\Models\Retrait;
use App\Models\User;
use App\Services\Paiement\ContexteVersement;
use App\Services\Paiement\EvenementPaiement;
use App\Services\Paiement\PaiementService;
use App\Services\Paiement\ResultatOperation;
use App\Services\Paiement\SessionPaiement;
use App\Services\RetraitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/** Prestataire actif connecté à l'API, avec un solde de départ. */
function prestataireRetirant(string|int|float $solde = 20000): Prestataire
{
    $prestataire = Prestataire::factory()->avoirSolde($solde)->for(User::factory()->prestataire(), 'user')->create();
    test()->actingAs($prestataire->user, 'sanctum');

    return $prestataire;
}

function demanderRetrait(array $donnees = [])
{
    return test()->postJson('/api/v1/prestataire/retraits', array_merge(['montant' => 5000, 'telephone_versement' => '771234567', 'operateur' => 'wave'], $donnees));
}

/**
 * Prestataire de paiement de test dont le versement est piloté par le test (échec définitif, exception, ou action au passage).
 * Injecté dans le conteneur par le test seulement : le pilote de production reste choisi par PAYMENT_DRIVER.
 * Le routeur garde le contrôleur (donc le service injecté) d'une requête à l'autre : on remplace le comportement du même double.
 */
function paiementQuiVerse(Closure $verser): void
{
    $actuel = app(PaiementService::class);
    if ($actuel instanceof PaiementDeTestRetrait) {
        $actuel->verser = $verser;

        return;
    }
    app()->instance(PaiementService::class, new PaiementDeTestRetrait($verser));
}

final class PaiementDeTestRetrait implements PaiementService
{
    public int $appels = 0;

    public function __construct(public Closure $verser) {}

    public function creerSession(string $reservationId, string $montantAcompte, string $description, ?string $paiementId = null): SessionPaiement
    {
        throw new LogicException('Non utilisé');
    }

    public function verifierSignature(string $corpsBrut, ?string $signature): bool
    {
        return false;
    }

    public function analyserEvenement(string $corpsBrut): EvenementPaiement
    {
        throw new LogicException('Non utilisé');
    }

    public function rembourser(string $reference, string $montant): ResultatOperation
    {
        throw new LogicException('Non utilisé');
    }

    public function consulterVersement(string $referenceMarchande): ?StatutRetrait
    {
        return null;
    }

    public function verser(string $montant, string $destinataire, ?ContexteVersement $contexte = null): ResultatOperation
    {
        $this->appels++;

        return ($this->verser)($montant, $destinataire);
    }
}

describe('accès', function () {
    it('renvoie 401 sans jeton, puis 403 pour un client et un administrateur, sans rien débiter', function () {
        $this->postJson('/api/v1/prestataire/retraits', ['montant' => 5000, 'telephone_versement' => '771234567'])->assertUnauthorized();

        foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum');
            demanderRetrait()->assertForbidden();
        }
        expect(Retrait::query()->count())->toBe(0);
    });
});

describe('validation', function () {
    it('accepte exactement les bornes 1 000 et 500 000', function (int $montant) {
        prestataireRetirant(600000);

        demanderRetrait(['montant' => $montant])->assertCreated();
    })->with([1000, 500000]);

    it('refuse 999 et 500 001 avec les bornes dans le message, sans débiter', function (int $montant, string $message) {
        $prestataire = prestataireRetirant(600000);

        demanderRetrait(['montant' => $montant])->assertUnprocessable()
            ->assertJsonPath('errors.montant.0', $message);

        expect($prestataire->fresh()->solde)->toBe('600000.00')->and(Retrait::query()->count())->toBe(0);
    })->with([
        'sous le minimum' => [999, 'Le montant minimum d\'un retrait est de 1 000 FCFA.'],
        'au-dessus du maximum' => [500001, 'Le montant maximum d\'un retrait est de 500 000 FCFA.'],
    ]);

    it('suit les bornes de la configuration', function () {
        config(['sopi.retraits.montant_minimum' => 2000, 'sopi.retraits.montant_maximum' => 3000]);
        prestataireRetirant();

        demanderRetrait(['montant' => 1999])->assertUnprocessable()->assertJsonPath('errors.montant.0', 'Le montant minimum d\'un retrait est de 2 000 FCFA.');
        demanderRetrait(['montant' => 3001])->assertUnprocessable()->assertJsonPath('errors.montant.0', 'Le montant maximum d\'un retrait est de 3 000 FCFA.');
        demanderRetrait(['montant' => 3000])->assertCreated();
    });

    it('refuse un montant absent, décimal ou textuel', function (mixed $montant) {
        prestataireRetirant();

        demanderRetrait(['montant' => $montant])->assertUnprocessable()->assertJsonValidationErrors('montant');
    })->with(['absent' => [null], 'décimal' => [5000.5], 'texte' => ['beaucoup'], 'tableau' => [[5000]]]);

    it('refuse un numéro absent ou invalide', function (mixed $numero) {
        prestataireRetirant();

        demanderRetrait(['telephone_versement' => $numero])->assertUnprocessable()->assertJsonValidationErrors('telephone_versement');
    })->with(['absent' => [null], 'fixe' => ['338201234'], 'trop court' => ['77123'], 'lettres' => ['abcdefghi'], 'mauvais préfixe' => ['791234567']]);

    it('normalise le numéro avant de l\'enregistrer', function (string $saisi) {
        prestataireRetirant();

        demanderRetrait(['telephone_versement' => $saisi])->assertCreated();

        expect(Retrait::query()->firstOrFail()->telephone_versement)->toBe('+221771234567');
    })->with(['77 123 45 67', '+221771234567', '00221771234567']);
});

describe('retrait réussi', function () {
    it('débite exactement le montant et renvoie le retrait réussi', function () {
        $prestataire = prestataireRetirant(20000);

        $reponse = demanderRetrait(['montant' => 5000])->assertCreated()
            ->assertJsonPath('statut', 'reussi')->assertJsonPath('montant', 5000)
            ->assertJsonPath('telephoneVersement', '+221******567');

        $retrait = Retrait::query()->firstOrFail();
        expect($prestataire->fresh()->solde)->toBe('15000.00')
            ->and($reponse->json('id'))->toBe($retrait->id)
            ->and($retrait->statut)->toBe(StatutRetrait::Reussi)
            ->and($retrait->reference_dexpay)->not->toBeNull()
            ->and($retrait->traite_le)->not->toBeNull()
            ->and($retrait->recredite_le)->toBeNull()
            ->and($reponse->json('dateTraitement'))->not->toBeNull();
        expect($reponse->getContent())->not->toContain($retrait->reference_dexpay);
    });

    it('accepte un solde exactement égal au montant et le ramène à zéro', function () {
        $prestataire = prestataireRetirant(5000);

        demanderRetrait(['montant' => 5000])->assertCreated()->assertJsonPath('statut', 'reussi');

        expect($prestataire->fresh()->solde)->toBe('0.00');
    });

    it('envoie le montant décimal et le numéro normalisé au prestataire de paiement', function () {
        $recu = [];
        prestataireRetirant();
        paiementQuiVerse(function ($montant, $destinataire) use (&$recu) {
            $recu = [$montant, $destinataire];

            return new ResultatOperation(true, 'REF-1');
        });

        demanderRetrait(['montant' => 2500])->assertCreated();

        expect($recu)->toBe(['2500.00', '+221771234567']);
    });

    it('ne touche pas au solde des autres prestataires', function () {
        $autre = Prestataire::factory()->avoirSolde(9000)->create();
        prestataireRetirant();

        demanderRetrait()->assertCreated();

        expect($autre->fresh()->solde)->toBe('9000.00');
    });
});

describe('solde insuffisant', function () {
    it('répond 422 sur la clé montant, sans créer de retrait ni débiter', function () {
        $prestataire = prestataireRetirant(4999);

        demanderRetrait(['montant' => 5000])->assertUnprocessable()
            ->assertJsonPath('message', 'Solde insuffisant.')
            ->assertJsonPath('errors.montant.0', 'Solde insuffisant.');

        expect($prestataire->fresh()->solde)->toBe('4999.00')->and(Retrait::query()->count())->toBe(0);
    });

    it('n\'appelle pas le prestataire de paiement', function () {
        prestataireRetirant(100);
        paiementQuiVerse(fn () => new ResultatOperation(true, 'REF'));

        demanderRetrait()->assertUnprocessable();

        expect(app(PaiementService::class)->appels)->toBe(0);
    });

    it('refuse la seconde de deux demandes rapides quand le solde ne suffit plus pour les deux', function () {
        $prestataire = prestataireRetirant(7000);

        demanderRetrait(['montant' => 5000])->assertCreated();
        demanderRetrait(['montant' => 5000])->assertUnprocessable()->assertJsonPath('errors.montant.0', 'Solde insuffisant.');

        expect($prestataire->fresh()->solde)->toBe('2000.00')->and(Retrait::query()->count())->toBe(1);
    });

    it('ne débite pas deux fois sur une lecture périmée du solde (débit conditionnel en base)', function () {
        $prestataire = prestataireRetirant(7000);
        $copiePerimee = Prestataire::query()->findOrFail($prestataire->id);
        $service = app(RetraitService::class);

        $service->demander($prestataire, 5000, '+221771234567', OperateurRetrait::Wave);

        // La copie lue avant le premier retrait croit encore avoir 7 000 : la condition SQL la refuse.
        expect($copiePerimee->solde)->toBe('7000.00')
            ->and(fn () => $service->demander($copiePerimee, 5000, '+221771234567', OperateurRetrait::Wave))->toThrow(ValidationException::class);

        expect($prestataire->fresh()->solde)->toBe('2000.00');
    });
});

describe('échec définitif du prestataire de paiement', function () {
    it('répond 503, passe le retrait à ECHOUE et recrédite le solde une seule fois', function () {
        $prestataire = prestataireRetirant(20000);
        paiementQuiVerse(fn () => new ResultatOperation(false));

        demanderRetrait(['montant' => 5000])->assertStatus(503)
            ->assertExactJson(['message' => 'Le versement n\'a pas pu être effectué. Votre solde n\'a pas été débité. Veuillez réessayer.']);

        $retrait = Retrait::query()->firstOrFail();
        expect($retrait->statut)->toBe(StatutRetrait::Echoue)
            ->and($retrait->traite_le)->not->toBeNull()
            ->and($retrait->recredite_le)->not->toBeNull()
            ->and($retrait->reference_dexpay)->toBeNull()
            ->and($prestataire->fresh()->solde)->toBe('20000.00');
    });

    it('permet de refaire la demande après un refus', function () {
        $prestataire = prestataireRetirant(6000);
        paiementQuiVerse(fn () => new ResultatOperation(false));
        demanderRetrait(['montant' => 5000])->assertStatus(503);

        paiementQuiVerse(fn () => new ResultatOperation(true, 'REF-OK'));
        demanderRetrait(['montant' => 5000])->assertCreated()->assertJsonPath('statut', 'reussi');

        expect($prestataire->fresh()->solde)->toBe('1000.00')->and(Retrait::query()->count())->toBe(2);
    });

    it('ne recrédite jamais deux fois, même si la garde est rejouée', function () {
        $prestataire = prestataireRetirant(20000);
        paiementQuiVerse(fn () => new ResultatOperation(false));
        demanderRetrait(['montant' => 5000])->assertStatus(503);
        $retrait = Retrait::query()->firstOrFail();
        $copiePerimee = Retrait::query()->findOrFail($retrait->id);

        // Rejeu : ni l'échec conditionnel ni la garde de recrédit ne passent une seconde fois.
        expect($retrait->echouerSiEnCours())->toBeFalse()
            ->and($retrait->recrediterSiPossible())->toBeFalse()
            ->and($copiePerimee->recrediterSiPossible())->toBeFalse();

        expect($prestataire->fresh()->solde)->toBe('20000.00');
    });

    it('ne pose la garde de recrédit que sur un retrait ECHOUE', function () {
        $enCours = Retrait::factory()->create();
        $reussi = Retrait::factory()->reussi()->create();

        expect($enCours->recrediterSiPossible())->toBeFalse()
            ->and($reussi->recrediterSiPossible())->toBeFalse()
            ->and($enCours->fresh()->recredite_le)->toBeNull()
            ->and($reussi->fresh()->recredite_le)->toBeNull();
    });

    it('ne repasse pas en ECHOUE un retrait déjà réussi (mise à jour conditionnelle)', function () {
        $prestataire = prestataireRetirant(20000);
        $retrait = null;
        // Pendant l'appel au prestataire, le retrait est réglé par ailleurs (webhook ou traitement manuel) avant le « refus ».
        paiementQuiVerse(function () use (&$retrait) {
            Retrait::query()->update(['statut' => 'reussi', 'reference_dexpay' => 'REF-AILLEURS', 'traite_le' => now()]);

            return new ResultatOperation(false);
        });

        demanderRetrait(['montant' => 5000])->assertStatus(503);

        $retrait = Retrait::query()->firstOrFail();
        expect($retrait->statut)->toBe(StatutRetrait::Reussi)
            ->and($retrait->recredite_le)->toBeNull()
            ->and($prestataire->fresh()->solde)->toBe('15000.00');
    });

    it('annule le recrédit si son écriture échoue (transaction) et laisse le retrait EN_COURS sans débit supplémentaire', function () {
        Log::spy();
        $prestataire = prestataireRetirant(20000);
        paiementQuiVerse(function () {
            DB::listen(function ($requete) {
                if (str_contains($requete->sql, 'solde + ')) {
                    throw new RuntimeException('Panne simulée : détail technique secret');
                }
            });

            return new ResultatOperation(false);
        });

        demanderRetrait(['montant' => 5000])->assertCreated()->assertJsonPath('statut', 'en_cours');

        $retrait = Retrait::query()->firstOrFail();
        expect($retrait->statut)->toBe(StatutRetrait::EnCours)
            ->and($retrait->recredite_le)->toBeNull()
            ->and($prestataire->fresh()->solde)->toBe('15000.00');
    });
});

describe('résultat ambigu', function () {
    it('ne recrédite pas : le retrait reste EN_COURS, le montant reste débité, et le journal est sans donnée sensible', function () {
        Log::spy();
        $prestataire = prestataireRetirant(20000);
        paiementQuiVerse(fn () => throw new RuntimeException('Délai dépassé vers 771234567 avec la clé SECRETE-123'));

        $reponse = demanderRetrait(['montant' => 5000, 'telephone_versement' => '771234567', 'operateur' => 'wave'])->assertCreated()
            ->assertJsonPath('statut', 'en_cours')->assertJsonPath('dateTraitement', null);

        $retrait = Retrait::query()->firstOrFail();
        expect($retrait->statut)->toBe(StatutRetrait::EnCours)
            ->and($retrait->recredite_le)->toBeNull()
            ->and($retrait->traite_le)->toBeNull()
            ->and($prestataire->fresh()->solde)->toBe('15000.00')
            ->and($reponse->json('id'))->toBe($retrait->id);

        Log::shouldHaveReceived('error')->once()->withArgs(function ($message, $contexte) use ($retrait) {
            $texte = $message.json_encode($contexte);

            return $contexte['retrait_id'] === $retrait->id
                && $contexte['exception'] === RuntimeException::class
                && ! str_contains($texte, '771234567') && ! str_contains($texte, 'SECRETE') && ! str_contains($texte, '5000');
        });
    });

    it('ne rejoue pas le versement ni le débit sur une nouvelle demande identique : elle est débitée à part, comme une vraie nouvelle demande', function () {
        $prestataire = prestataireRetirant(20000);
        paiementQuiVerse(fn () => throw new RuntimeException('Délai dépassé'));

        demanderRetrait(['montant' => 5000])->assertCreated();
        demanderRetrait(['montant' => 5000])->assertCreated();

        expect($prestataire->fresh()->solde)->toBe('10000.00')->and(Retrait::query()->count())->toBe(2);
    });

    it('reste EN_COURS sans recrédit si l\'enregistrement du succès échoue après un versement accepté', function () {
        Log::spy();
        $prestataire = prestataireRetirant(20000);
        paiementQuiVerse(function () {
            // Déclencheur SQLite : la mise à jour du retrait est refusée AVANT d'être appliquée (comme une panne de base).
            DB::unprepared("CREATE TRIGGER panne_retraits BEFORE UPDATE ON retraits BEGIN SELECT RAISE(ABORT, 'panne simulée'); END");

            return new ResultatOperation(true, 'REF-PARTI');
        });

        demanderRetrait(['montant' => 5000])->assertCreated()->assertJsonPath('statut', 'en_cours');

        expect(Retrait::query()->firstOrFail()->statut)->toBe(StatutRetrait::EnCours)
            ->and($prestataire->fresh()->solde)->toBe('15000.00');
    });
});

describe('limitation de débit', function () {
    it('accepte 5 demandes par minute puis répond 429', function () {
        prestataireRetirant(100000);

        foreach (range(1, 5) as $i) {
            demanderRetrait(['montant' => 1000])->assertCreated();
        }
        demanderRetrait(['montant' => 1000])->assertStatus(429);

        expect(Retrait::query()->count())->toBe(5);
    });

    it('compte par utilisateur : un autre prestataire n\'est pas bloqué', function () {
        prestataireRetirant(100000);
        foreach (range(1, 5) as $i) {
            demanderRetrait(['montant' => 1000])->assertCreated();
        }
        demanderRetrait(['montant' => 1000])->assertStatus(429);

        prestataireRetirant(100000);
        demanderRetrait(['montant' => 1000])->assertCreated();
    });
});
