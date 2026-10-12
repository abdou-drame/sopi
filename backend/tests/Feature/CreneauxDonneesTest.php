<?php

use App\Enums\StatutCreneau;
use App\Models\Creneau;
use App\Models\Prestataire;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('table creneaux', function () {
    it('a les colonnes prévues, sans colonne supplémentaire', function () {
        expect(Schema::getColumnListing('creneaux'))->toEqualCanonicalizing([
            'id', 'prestataire_id', 'date_debut', 'date_fin', 'statut', 'verrou_expire_a', 'created_at', 'updated_at',
        ]);
    });

    it('utilise des identifiants UUID et le statut libre par défaut', function () {
        $prestataire = Prestataire::factory()->create();
        $debut = now()->addDay()->startOfHour();

        $creneau = new Creneau(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
        $creneau->prestataire_id = $prestataire->id;
        $creneau->save();

        $recharge = $creneau->fresh();
        expect($recharge->id)->toBeUuid()
            ->and($recharge->statut)->toBe(StatutCreneau::Libre)
            ->and($recharge->verrou_expire_a)->toBeNull();
    });

    it('refuse une fin qui n\'est pas après le début', function (int $minutes) {
        $debut = now()->addDay()->startOfHour();

        expect(fn () => Creneau::factory()->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addMinutes($minutes)]))
            ->toThrow(InvalidArgumentException::class, 'La fin d\'un créneau doit être postérieure à son début.');

        expect(Creneau::count())->toBe(0);
    })->with(['fin égale au début' => [0], 'fin avant le début' => [-30]]);

    it('refuse de modifier un créneau avec une fin avant le début', function () {
        $creneau = Creneau::factory()->create();

        expect(fn () => $creneau->update(['date_fin' => $creneau->date_debut->copy()->subMinute()]))
            ->toThrow(InvalidArgumentException::class);
    });

    it('refuse deux créneaux au même début chez un même prestataire', function () {
        $prestataire = Prestataire::factory()->create();
        $debut = now()->addDay()->startOfHour();
        Creneau::factory()->for($prestataire)->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);

        expect(fn () => Creneau::factory()->for($prestataire)->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addMinutes(30)]))
            ->toThrow(QueryException::class);
    });

    it('accepte le même début chez deux prestataires différents', function () {
        $debut = now()->addDay()->startOfHour();
        $donnees = ['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()];

        Creneau::factory()->create($donnees);
        Creneau::factory()->create($donnees);

        expect(Creneau::count())->toBe(2);
    });

    it('supprime les créneaux avec le prestataire (cascade)', function () {
        $prestataire = Prestataire::factory()->create();
        $autre = Prestataire::factory()->create();
        Creneau::factory()->count(3)->for($prestataire)->create();
        Creneau::factory()->for($autre)->create();

        $prestataire->delete();

        expect(Creneau::count())->toBe(1)
            ->and(Creneau::first()->prestataire_id)->toBe($autre->id);
    });

    it('a les index prévus', function () {
        $index = collect(Schema::getIndexes('creneaux'))->map(fn ($i) => $i['columns'])->all();

        expect($index)->toContain(['prestataire_id', 'date_debut', 'statut'])
            ->and($index)->toContain(['statut', 'verrou_expire_a'])
            ->and($index)->toContain(['prestataire_id', 'date_debut']);
    });
});

describe('modèle Creneau', function () {
    it('est relié à son prestataire dans les deux sens', function () {
        $prestataire = Prestataire::factory()->create();
        $creneaux = Creneau::factory()->count(2)->for($prestataire)->create();

        expect($creneaux[0]->prestataire->is($prestataire))->toBeTrue()
            ->and($prestataire->creneaux)->toHaveCount(2)
            ->and($prestataire->creneaux->pluck('id')->all())->toEqualCanonicalizing($creneaux->pluck('id')->all());
    });

    it('convertit les dates en Carbon et le statut en enum', function () {
        $creneau = Creneau::factory()->verrouille()->create()->fresh();

        expect($creneau->date_debut)->toBeInstanceOf(Carbon::class)
            ->and($creneau->date_fin)->toBeInstanceOf(Carbon::class)
            ->and($creneau->verrou_expire_a)->toBeInstanceOf(Carbon::class)
            ->and($creneau->statut)->toBe(StatutCreneau::Verrouille);
    });

    it('ne laisse pas assigner le statut, le verrou ni le prestataire en masse', function () {
        $prestataire = Prestataire::factory()->create();
        $debut = now()->addDay()->startOfHour();

        $creneau = new Creneau([
            'date_debut' => $debut, 'date_fin' => $debut->copy()->addHour(),
            'statut' => 'reserve', 'verrou_expire_a' => now()->addHour(), 'prestataire_id' => $prestataire->id,
        ]);

        expect($creneau->statut)->toBe(StatutCreneau::Libre)
            ->and($creneau->verrou_expire_a)->toBeNull()
            ->and($creneau->prestataire_id)->toBeNull();
    });

    describe('estLibre()', function () {
        it('est vrai pour un créneau libre', function () {
            expect(Creneau::factory()->libre()->create()->estLibre())->toBeTrue();
        });

        it('est faux pour un créneau réservé ou indisponible', function () {
            expect(Creneau::factory()->reserve()->create()->estLibre())->toBeFalse()
                ->and(Creneau::factory()->indisponible()->create()->estLibre())->toBeFalse();
        });

        it('est faux pour un créneau verrouillé dont le verrou court encore', function () {
            expect(Creneau::factory()->verrouille()->create()->estLibre())->toBeFalse();
        });

        it('est vrai pour un créneau verrouillé dont le verrou est expiré', function () {
            expect(Creneau::factory()->verrouille(now()->subSecond())->create()->estLibre())->toBeTrue();
        });

        it('est faux pour un créneau verrouillé sans date d\'expiration', function () {
            expect(Creneau::factory()->verrouille()->create(['verrou_expire_a' => null])->estLibre())->toBeFalse();
        });
    });

    describe('scopes', function () {
        it('libres() suit la même règle que estLibre()', function () {
            $prestataire = Prestataire::factory()->create();
            $libre = Creneau::factory()->for($prestataire)->libre()->create();
            $expire = Creneau::factory()->for($prestataire)->verrouille(now()->subMinute())->create();
            Creneau::factory()->for($prestataire)->verrouille()->create();
            Creneau::factory()->for($prestataire)->reserve()->create();
            Creneau::factory()->for($prestataire)->indisponible()->create();

            $ids = Creneau::libres()->pluck('id')->all();

            expect($ids)->toEqualCanonicalizing([$libre->id, $expire->id])
                ->and(Creneau::all()->filter->estLibre()->pluck('id')->all())->toEqualCanonicalizing($ids);
        });

        it('aVenir() exclut les créneaux déjà commencés', function () {
            $prestataire = Prestataire::factory()->create();
            $futur = Creneau::factory()->for($prestataire)->create();
            $passeDebut = now()->subHours(3)->startOfHour();
            Creneau::factory()->for($prestataire)->create(['date_debut' => $passeDebut, 'date_fin' => $passeDebut->copy()->addHour()]);

            expect(Creneau::aVenir()->pluck('id')->all())->toBe([$futur->id]);
        });
    });
});

describe('factory', function () {
    it('crée un créneau libre futur de 60 minutes par défaut', function () {
        $creneau = Creneau::factory()->create();

        expect($creneau->statut)->toBe(StatutCreneau::Libre)
            ->and($creneau->date_debut->isFuture())->toBeTrue()
            ->and((int) $creneau->date_debut->diffInMinutes($creneau->date_fin))->toBe(60)
            ->and($creneau->verrou_expire_a)->toBeNull();
    });

    it('fournit les quatre états', function () {
        $etats = [
            'libre' => [Creneau::factory()->libre()->create(), StatutCreneau::Libre, false],
            'verrouille' => [Creneau::factory()->verrouille()->create(), StatutCreneau::Verrouille, true],
            'reserve' => [Creneau::factory()->reserve()->create(), StatutCreneau::Reserve, false],
            'indisponible' => [Creneau::factory()->indisponible()->create(), StatutCreneau::Indisponible, false],
        ];

        foreach ($etats as [$creneau, $statut, $verrou]) {
            expect($creneau->statut)->toBe($statut)
                ->and($creneau->verrou_expire_a !== null)->toBe($verrou)
                ->and($creneau->date_debut->isFuture())->toBeTrue();
        }
    });

    it('crée plusieurs créneaux pour un même prestataire sans collision', function () {
        $prestataire = Prestataire::factory()->create();

        Creneau::factory()->count(10)->for($prestataire)->create();

        expect($prestataire->creneaux()->count())->toBe(10);
    });
});
