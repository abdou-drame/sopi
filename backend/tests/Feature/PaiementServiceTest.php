<?php

use App\Enums\StatutPaiement;
use App\Exceptions\ConfigurationPaiementInvalide;
use App\Exceptions\EvenementPaiementIllisible;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Services\Paiement\CalculPaiement;
use App\Services\Paiement\DexPay\DexPayPaiementService;
use App\Services\Paiement\EvenementPaiement;
use App\Services\Paiement\FakePaiementService;
use App\Services\Paiement\PaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('calcul des montants', function () {
    it('donne 3 000 et 1 000 pour 10 000 FCFA avec les taux par défaut', function () {
        $montants = app(CalculPaiement::class)->calculer('10000.00');

        expect($montants->montantAcompte)->toBe('3000.00')->and($montants->commission)->toBe('1000.00');
    });

    it('arrondit au FCFA entier, demi vers le haut', function (string $prix, string $acompte, string $commission) {
        $montants = app(CalculPaiement::class)->calculer($prix);

        expect($montants->montantAcompte)->toBe($acompte)->and($montants->commission)->toBe($commission);
    })->with([
        'prix 12 345' => ['12345', '3704.00', '1235.00'],       // 3703,5 -> 3704 ; 1234,5 -> 1235
        'avec centimes' => ['12345.50', '3704.00', '1235.00'],   // 3703,65 -> 3704 ; 1234,55 -> 1235
        'juste sous la moitié' => ['1648', '494.00', '165.00'],  // 494,4 -> 494 ; 164,8 -> 165
        'moitié exacte acompte' => ['5', '2.00', '1.00'],        // 1,5 -> 2 ; 0,5 -> 1
    ]);

    it('garantit un acompte d\'au moins 1 FCFA et une commission qui ne le dépasse pas', function () {
        $montants = app(CalculPaiement::class)->calculer('1');

        expect($montants->montantAcompte)->toBe('1.00')->and($montants->commission)->toBe('0.00');

        $centime = app(CalculPaiement::class)->calculer('0.01');
        expect($centime->montantAcompte)->toBe('1.00')->and($centime->commission)->toBe('0.00');
    });

    it('supporte le plus grand prix de decimal(12,2)', function () {
        $montants = app(CalculPaiement::class)->calculer('9999999999.99');

        expect($montants->montantAcompte)->toBe('3000000000.00')->and($montants->commission)->toBe('1000000000.00');
    });

    it('utilise les taux de la configuration', function () {
        config(['sopi.paiement.acompte_taux' => 0.5, 'sopi.paiement.commission_taux' => 0.2]);

        $montants = app(CalculPaiement::class)->calculer('10000');

        expect($montants->montantAcompte)->toBe('5000.00')->and($montants->commission)->toBe('2000.00');
    });

    it('accepte un acompte égal au prix et une commission égale à l\'acompte', function () {
        $montants = (new CalculPaiement(1.0, 1.0))->calculer('2500');

        expect($montants->montantAcompte)->toBe('2500.00')->and($montants->commission)->toBe('2500.00');
    });

    it('refuse une configuration invalide', function (float $acompte, float $commission) {
        expect(fn () => new CalculPaiement($acompte, $commission))->toThrow(ConfigurationPaiementInvalide::class);
    })->with([
        'commission nulle' => [0.3, 0.0],
        'commission négative' => [0.3, -0.1],
        'commission > acompte' => [0.1, 0.3],
        'acompte > 1' => [1.2, 0.1],
        'acompte nul' => [0.0, 0.0],
    ]);

    it('refuse une configuration invalide dès la résolution depuis le conteneur', function () {
        config(['sopi.paiement.acompte_taux' => 0.05, 'sopi.paiement.commission_taux' => 0.10]);

        expect(fn () => app(CalculPaiement::class))->toThrow(ConfigurationPaiementInvalide::class);
    });

    it('refuse un prix invalide', function (string $prix) {
        expect(fn () => app(CalculPaiement::class)->calculer($prix))->toThrow(InvalidArgumentException::class);
    })->with(['0', '0.00', '-100', 'abc', '', '12.345', '1e5', '99999999999']);

    it('donne toujours des montants acceptés par les contraintes de la table paiements', function (string $prix) {
        $montants = app(CalculPaiement::class)->calculer($prix);
        $reservation = Reservation::factory()->acceptee()->create();

        $paiement = Paiement::factory()->create([
            'reservation_id' => $reservation->id,
            'montant_acompte' => $montants->montantAcompte,
            'commission' => $montants->commission,
        ]);

        expect($paiement->fresh()->montant_acompte)->toBe($montants->montantAcompte);
    })->with(['0.01', '1', '2', '3', '5', '999', '10000', '12345', '9999999999.99']);
});

describe('résolution du pilote', function () {
    it('donne le faux service avec fake', function () {
        config(['sopi.paiement.driver' => 'fake']);
        app()->forgetInstance(PaiementService::class);

        expect(app(PaiementService::class))->toBeInstanceOf(FakePaiementService::class);
    });

    it('refuse dexpay sans clés, en nommant les variables manquantes', function () {
        config(['sopi.paiement.driver' => 'dexpay', 'sopi.paiement.dexpay.public_key' => '', 'sopi.paiement.dexpay.secret_key' => '']);
        app()->forgetInstance(PaiementService::class);

        expect(fn () => app(PaiementService::class))
            ->toThrow(ConfigurationPaiementInvalide::class, 'DEXPAY_PUBLIC_KEY, DEXPAY_SECRET_KEY');
    });

    it('donne le service DexPay quand les clés sont présentes', function () {
        config(['sopi.paiement.driver' => 'dexpay', 'sopi.paiement.dexpay.public_key' => 'pk_factice', 'sopi.paiement.dexpay.secret_key' => 'sk_factice']);
        app()->forgetInstance(PaiementService::class);

        expect(app(PaiementService::class))->toBeInstanceOf(DexPayPaiementService::class);
    });

    it('refuse une valeur inconnue', function () {
        config(['sopi.paiement.driver' => 'paypal']);
        app()->forgetInstance(PaiementService::class);

        expect(fn () => app(PaiementService::class))->toThrow(ConfigurationPaiementInvalide::class, 'paypal');
    });

    it('utilise fake par défaut et un secret vide par défaut quand les variables sont absentes', function () {
        $depot = Env::getRepository();
        $sauvegarde = [];
        foreach (['PAYMENT_DRIVER', 'DEXPAY_SECRET_KEY'] as $nom) {
            $sauvegarde[$nom] = $depot->get($nom);
            $depot->clear($nom);
        }

        try {
            $paiement = require config_path('sopi.php');
        } finally {
            foreach ($sauvegarde as $nom => $valeur) {
                if ($valeur !== null) {
                    $depot->set($nom, $valeur);
                }
            }
        }

        expect($paiement['paiement']['driver'])->toBe('fake')
            ->and($paiement['paiement']['webhook_secret'])->toBe('');
    });
});

describe('FakePaiementService', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
        $this->secret = 'secret-de-test';
        $this->service = new FakePaiementService($this->secret);
        $this->corps = '{"reference":"FAKE-ABC","statut":"reussi","montant":"3000.00"}';
        $this->signer = fn (string $corps, ?string $secret = null) => hash_hmac('sha256', $corps, $secret ?? $this->secret);
    });

    it('crée des sessions avec des références uniques préfixées, sans requête HTTP', function () {
        $sessions = collect(range(1, 50))->map(fn () => $this->service->creerSession('res-1', '3000.00', 'Acompte'));
        $references = $sessions->pluck('reference');

        expect($references->unique())->toHaveCount(50)
            ->and($references->every(fn ($r) => str_starts_with($r, 'FAKE-')))->toBeTrue()
            ->and($sessions->first()->urlPaiement)->toContain($sessions->first()->reference)
            ->and($sessions->first()->urlPaiement)->toContain('.invalid');
        Http::assertNothingSent();
    });

    it('accepte une signature valide', function () {
        expect($this->service->verifierSignature($this->corps, ($this->signer)($this->corps)))->toBeTrue();
    });

    it('refuse un corps modifié', function () {
        $signature = ($this->signer)($this->corps);

        expect($this->service->verifierSignature(str_replace('3000', '1', $this->corps), $signature))->toBeFalse();
    });

    it('refuse une signature fausse, absente ou vide', function (?string $signature) {
        expect($this->service->verifierSignature($this->corps, $signature))->toBeFalse();
    })->with([null, '', 'pas-une-signature', str_repeat('0', 64)]);

    it('refuse une signature calculée avec un autre secret', function () {
        expect($this->service->verifierSignature($this->corps, ($this->signer)($this->corps, 'autre')))->toBeFalse();
    });

    it('refuse tout quand le secret est vide ou absent, même si la signature « semble » correcte', function (?string $secret) {
        $service = new FakePaiementService($secret);

        expect($service->verifierSignature($this->corps, hash_hmac('sha256', $this->corps, '')))->toBeFalse()
            ->and($service->verifierSignature($this->corps, ($this->signer)($this->corps)))->toBeFalse()
            ->and($service->verifierSignature($this->corps, null))->toBeFalse();
    })->with([null, '']);

    it('analyse un corps valide', function () {
        $evenement = $this->service->analyserEvenement($this->corps);

        expect($evenement)->toBeInstanceOf(EvenementPaiement::class)
            ->and($evenement->reference)->toBe('FAKE-ABC')
            ->and($evenement->statut)->toBe(StatutPaiement::Reussi)
            ->and($evenement->montant)->toBe('3000.00');
    });

    it('analyse un échec et normalise le montant', function () {
        $evenement = $this->service->analyserEvenement('{"reference":"FAKE-X","statut":"echoue","montant":3000}');

        expect($evenement->statut)->toBe(StatutPaiement::Echoue)->and($evenement->montant)->toBe('3000.00');
    });

    it('refuse un corps illisible ou incomplet', function (string $corps) {
        expect(fn () => $this->service->analyserEvenement($corps))->toThrow(EvenementPaiementIllisible::class);
    })->with([
        'pas du JSON' => ['pas du json'],
        'vide' => [''],
        'scalaire' => ['42'],
        'sans référence' => ['{"statut":"reussi","montant":"3000.00"}'],
        'référence vide' => ['{"reference":"","statut":"reussi","montant":"3000.00"}'],
        'sans statut' => ['{"reference":"FAKE-A","montant":"3000.00"}'],
        'statut inconnu' => ['{"reference":"FAKE-A","statut":"rembourse","montant":"3000.00"}'],
        'sans montant' => ['{"reference":"FAKE-A","statut":"reussi"}'],
        'montant nul' => ['{"reference":"FAKE-A","statut":"reussi","montant":"0"}'],
        'montant négatif' => ['{"reference":"FAKE-A","statut":"reussi","montant":"-5"}'],
        'montant texte' => ['{"reference":"FAKE-A","statut":"reussi","montant":"beaucoup"}'],
    ]);

    it('rembourse et verse avec une référence factice, et refuse les demandes invalides', function () {
        $remboursement = $this->service->rembourser('FAKE-ABC', '3000.00');
        $versement = $this->service->verser('5000.00', '770000000');

        expect($remboursement->succes)->toBeTrue()->and($remboursement->reference)->toStartWith('FAKE-')
            ->and($versement->succes)->toBeTrue()->and($versement->reference)->toStartWith('FAKE-')
            ->and($remboursement->reference)->not->toBe($versement->reference)
            ->and($this->service->rembourser('FAKE-ABC', '0')->succes)->toBeFalse()
            ->and($this->service->rembourser('', '100')->succes)->toBeFalse()
            ->and($this->service->verser('-1', '770000000')->succes)->toBeFalse()
            ->and($this->service->verser('100', '')->reference)->toBeNull();
        Http::assertNothingSent();
    });

    it('n\'écrit rien en base en analysant un événement', function () {
        Paiement::factory()->create();
        $avant = [DB::table('paiements')->get()->toJson(), DB::table('reservations')->get()->toJson()];

        $this->service->analyserEvenement($this->corps);
        $this->service->creerSession('res-1', '3000.00', 'Acompte');

        expect([DB::table('paiements')->get()->toJson(), DB::table('reservations')->get()->toJson()])->toBe($avant);
    });

    it('est fourni par le conteneur avec le secret de la configuration', function () {
        config(['sopi.paiement.driver' => 'fake', 'sopi.paiement.webhook_secret' => 'secret-conteneur']);
        app()->forgetInstance(PaiementService::class);
        $service = app(PaiementService::class);

        expect($service->verifierSignature($this->corps, hash_hmac('sha256', $this->corps, 'secret-conteneur')))->toBeTrue()
            ->and($service->verifierSignature($this->corps, ($this->signer)($this->corps)))->toBeFalse();
    });
});
