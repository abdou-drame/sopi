<?php

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('table paiements', function () {
    it('a les colonnes prévues', function () {
        expect(Schema::getColumnListing('paiements'))->toEqualCanonicalizing([
            'id', 'reservation_id', 'montant_acompte', 'commission', 'statut', 'reference_dexpay', 'url_paiement', 'paye_le', 'rembourse_le', 'remboursement_du_le', 'remboursement_tentatives', 'credite_le', 'created_at', 'updated_at',
        ]);
    });

    it('crée un paiement via la factory avec un UUID, le statut en_attente et des montants cohérents', function () {
        $paiement = Paiement::factory()->create()->fresh();

        expect($paiement->id)->toBeUuid()
            ->and($paiement->statut)->toBe(StatutPaiement::EnAttente)
            ->and($paiement->montant_acompte)->toBe('3000.00')
            ->and($paiement->commission)->toBe('1000.00')
            ->and($paiement->reference_dexpay)->toBeNull()
            ->and($paiement->paye_le)->toBeNull()
            ->and($paiement->rembourse_le)->toBeNull()
            ->and($paiement->created_at)->not->toBeNull();
    });

    it('applique le statut en_attente par défaut en base', function () {
        $paiement = new Paiement;
        $paiement->reservation_id = Reservation::factory()->acceptee()->create()->id;
        $paiement->montant_acompte = 3000;
        $paiement->commission = 1000;
        $paiement->save();

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::EnAttente);
    });

    it('a les index prévus', function () {
        $colonnes = collect(Schema::getIndexes('paiements'))->map(fn ($i) => implode(',', $i['columns']))->all();

        expect($colonnes)->toContain('statut,created_at', 'reservation_id', 'reference_dexpay');
    });

    it('fournit des états cohérents', function () {
        $reussi = Paiement::factory()->reussi()->create()->fresh();
        $echoue = Paiement::factory()->echoue()->create()->fresh();
        $rembourse = Paiement::factory()->rembourse()->create()->fresh();

        expect($reussi->statut)->toBe(StatutPaiement::Reussi)
            ->and($reussi->paye_le)->not->toBeNull()
            ->and($reussi->rembourse_le)->toBeNull()
            ->and($reussi->reference_dexpay)->not->toBeNull()
            ->and($echoue->statut)->toBe(StatutPaiement::Echoue)
            ->and($echoue->paye_le)->toBeNull()
            ->and($rembourse->statut)->toBe(StatutPaiement::Rembourse)
            ->and($rembourse->paye_le)->not->toBeNull()
            ->and($rembourse->rembourse_le)->not->toBeNull();
    });
});

describe('contraintes de cohérence', function () {
    it('refuse un acompte nul ou négatif', function (string $montant) {
        expect(fn () => Paiement::factory()->create(['montant_acompte' => $montant, 'commission' => '0.00']))->toThrow(QueryException::class);
    })->with(['0.00', '-100.00']);

    it('refuse une commission négative', function () {
        expect(fn () => Paiement::factory()->create(['commission' => '-1.00']))->toThrow(QueryException::class);
    });

    it('refuse une commission supérieure à l\'acompte', function () {
        expect(fn () => Paiement::factory()->create(['montant_acompte' => '1000.00', 'commission' => '1000.01']))->toThrow(QueryException::class);
    });

    it('accepte une commission nulle ou égale à l\'acompte', function () {
        Paiement::factory()->create(['commission' => '0.00']);
        Paiement::factory()->create(['montant_acompte' => '500.00', 'commission' => '500.00']);

        expect(Paiement::count())->toBe(2);
    });

    it('refuse un statut invalide', function () {
        $paiement = Paiement::factory()->create();

        expect(fn () => DB::table('paiements')->where('id', $paiement->id)->update(['statut' => 'inconnu']))->toThrow(QueryException::class);
        expect(fn () => DB::table('paiements')->insert([
            'id' => (string) Str::uuid(), 'reservation_id' => Reservation::factory()->acceptee()->create()->id,
            'montant_acompte' => 3000, 'commission' => 1000, 'statut' => 'inconnu',
        ]))->toThrow(QueryException::class);
    });

    it('refuse aussi une mise à jour qui rend les montants incohérents', function () {
        $paiement = Paiement::factory()->create();

        expect(fn () => DB::table('paiements')->where('id', $paiement->id)->update(['commission' => 5000]))->toThrow(QueryException::class);
    });
});

describe('référence DexPay', function () {
    it('est unique', function () {
        Paiement::factory()->reussi()->create(['reference_dexpay' => 'DXP-UNIQUE']);

        expect(fn () => Paiement::factory()->reussi()->create(['reference_dexpay' => 'DXP-UNIQUE']))->toThrow(QueryException::class);
    });

    it('peut rester vide pour plusieurs paiements', function () {
        Paiement::factory()->count(2)->create();

        expect(Paiement::whereNull('reference_dexpay')->count())->toBe(2);
    });
});

describe('un seul paiement actif par réservation', function () {
    it('refuse un second paiement en_attente ou reussi', function (string $premier, string $second) {
        $reservation = Reservation::factory()->acceptee()->create();
        Paiement::factory()->{$premier}()->create(['reservation_id' => $reservation->id]);

        expect(fn () => Paiement::factory()->{$second}()->create(['reservation_id' => $reservation->id]))->toThrow(QueryException::class);
    })->with([
        ['enAttente', 'enAttente'],
        ['enAttente', 'reussi'],
        ['reussi', 'enAttente'],
        ['reussi', 'reussi'],
    ]);

    it('autorise un nouveau paiement après un paiement échoué ou remboursé', function (string $ancien) {
        $reservation = Reservation::factory()->acceptee()->create();
        Paiement::factory()->{$ancien}()->create(['reservation_id' => $reservation->id]);
        Paiement::factory()->enAttente()->create(['reservation_id' => $reservation->id]);

        expect($reservation->paiements()->count())->toBe(2);
    })->with(['echoue', 'rembourse']);

    it('autorise plusieurs paiements échoués ou remboursés sur la même réservation', function () {
        $reservation = Reservation::factory()->acceptee()->create();
        Paiement::factory()->echoue()->count(2)->create(['reservation_id' => $reservation->id]);
        Paiement::factory()->rembourse()->create(['reservation_id' => $reservation->id]);

        expect($reservation->paiements()->count())->toBe(3);
    });

    it('refuse de réactiver un paiement quand un autre est déjà actif', function () {
        $reservation = Reservation::factory()->acceptee()->create();
        $echoue = Paiement::factory()->echoue()->create(['reservation_id' => $reservation->id]);
        Paiement::factory()->enAttente()->create(['reservation_id' => $reservation->id]);

        expect(fn () => DB::table('paiements')->where('id', $echoue->id)->update(['statut' => 'en_attente']))->toThrow(QueryException::class);
    });

    it('autorise des paiements actifs sur des réservations différentes', function () {
        Paiement::factory()->count(2)->create();

        expect(Paiement::count())->toBe(2);
    });
});

describe('clé étrangère', function () {
    it('refuse une réservation inexistante', function () {
        expect(fn () => Paiement::factory()->create(['reservation_id' => (string) Str::uuid()]))->toThrow(QueryException::class);
    });

    it('refuse la suppression d\'une réservation qui a un paiement', function () {
        $paiement = Paiement::factory()->create();

        expect(fn () => $paiement->reservation->delete())->toThrow(QueryException::class);
        expect(Reservation::find($paiement->reservation_id))->not->toBeNull();
    });
});

describe('relations', function () {
    it('relie le paiement à sa réservation et inversement', function () {
        $paiement = Paiement::factory()->create();

        expect($paiement->reservation)->toBeInstanceOf(Reservation::class)
            ->and($paiement->reservation->paiements->pluck('id')->all())->toBe([$paiement->id]);
    });

    it('expose le paiement actif en lecture seule', function () {
        $reservation = Reservation::factory()->acceptee()->create();
        Paiement::factory()->echoue()->create(['reservation_id' => $reservation->id]);

        expect($reservation->paiementActif()->first())->toBeNull();

        $actif = Paiement::factory()->reussi()->create(['reservation_id' => $reservation->id]);

        expect($reservation->paiementActif()->first()->id)->toBe($actif->id)
            ->and($reservation->paiements()->count())->toBe(2);
    });
});

describe('modèle', function () {
    it('convertit statut, montants et dates', function () {
        $paiement = Paiement::factory()->rembourse()->create(['montant_acompte' => '4500.5', 'commission' => '1500'])->fresh();

        expect($paiement->statut)->toBeInstanceOf(StatutPaiement::class)
            ->and($paiement->montant_acompte)->toBe('4500.50')
            ->and($paiement->commission)->toBe('1500.00')
            ->and($paiement->paye_le)->toBeInstanceOf(DateTimeInterface::class)
            ->and($paiement->rembourse_le)->toBeInstanceOf(DateTimeInterface::class);
    });

    it('ne laisse rien assigner en masse', function () {
        foreach ([['statut' => 'reussi'], ['montant_acompte' => 1], ['commission' => 1], ['reservation_id' => 'x'], ['reference_dexpay' => 'x'], ['paye_le' => now()]] as $attributs) {
            expect(fn () => new Paiement($attributs))->toThrow(MassAssignmentException::class);
        }
    });

    it('sait si un statut est actif', function () {
        expect(StatutPaiement::EnAttente->estActif())->toBeTrue()
            ->and(StatutPaiement::Reussi->estActif())->toBeTrue()
            ->and(StatutPaiement::Echoue->estActif())->toBeFalse()
            ->and(StatutPaiement::Rembourse->estActif())->toBeFalse();
    });
});

describe('configuration', function () {
    it('fournit les taux par défaut de l\'acompte et de la commission', function () {
        expect(config('sopi.paiement.acompte_taux'))->toBe(0.30)
            ->and(config('sopi.paiement.commission_taux'))->toBe(0.10);
    });
});
