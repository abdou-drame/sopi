<?php

use App\Enums\StatutReservation;
use App\Models\Creneau;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('table reservations', function () {
    it('a les colonnes prévues', function () {
        expect(Schema::getColumnListing('reservations'))->toEqualCanonicalizing([
            'id', 'client_id', 'creneau_id', 'prestation_id', 'prix_applique', 'statut', 'motif_refus', 'code_reservation', 'acceptee_le', 'created_at', 'updated_at',
        ]);
    });

    it('utilise un UUID et le statut en_attente par défaut', function () {
        $creneau = Creneau::factory()->create();
        $reservation = new Reservation;
        $reservation->client_id = User::factory()->create()->id;
        $reservation->creneau_id = $creneau->id;
        $prestation = Prestation::factory()->for($creneau->prestataire)->create();
        $reservation->prestation_id = $prestation->id;
        $reservation->prix_applique = $prestation->prix;
        $reservation->save();

        $recharge = $reservation->fresh();
        expect($recharge->id)->toBeUuid()
            ->and($recharge->statut)->toBe(StatutReservation::EnAttente)
            ->and($recharge->motif_refus)->toBeNull()
            ->and($recharge->code_reservation)->toBeNull()
            ->and($recharge->acceptee_le)->toBeNull()
            ->and($recharge->created_at)->not->toBeNull();
    });

    it('ne laisse assigner en masse ni statut, ni code, ni clés', function () {
        foreach ([['statut' => 'terminee'], ['code_reservation' => 'ABC123'], ['client_id' => 'x'], ['motif_refus' => 'x'], ['acceptee_le' => now()]] as $attributs) {
            expect(fn () => new Reservation($attributs))->toThrow(MassAssignmentException::class);
        }
    });

    it('a les index prévus', function () {
        $colonnes = collect(Schema::getIndexes('reservations'))->map(fn ($i) => implode(',', $i['columns']))->all();

        expect($colonnes)->toContain('client_id,statut', 'statut,created_at', 'prestation_id', 'code_reservation', 'creneau_id');
    });
});

describe('relations', function () {
    it('relie la réservation à son client, son créneau et sa prestation, et inversement', function () {
        $reservation = Reservation::factory()->create();

        expect($reservation->client)->toBeInstanceOf(User::class)
            ->and($reservation->creneau)->toBeInstanceOf(Creneau::class)
            ->and($reservation->prestation)->toBeInstanceOf(Prestation::class)
            ->and($reservation->client->reservations->pluck('id')->all())->toBe([$reservation->id])
            ->and($reservation->creneau->reservations->pluck('id')->all())->toBe([$reservation->id])
            ->and($reservation->prestation->reservations->pluck('id')->all())->toBe([$reservation->id]);
    });
});

describe('code de réservation', function () {
    it('est unique', function () {
        Reservation::factory()->confirmee()->create(['code_reservation' => 'AB12CD']);

        expect(fn () => Reservation::factory()->confirmee()->create(['code_reservation' => 'AB12CD']))->toThrow(QueryException::class);
    });

    it('accepte plusieurs réservations sans code', function () {
        Reservation::factory()->count(2)->create();

        expect(Reservation::whereNull('code_reservation')->count())->toBe(2);
    });
});

describe('une seule réservation active par créneau', function () {
    it('refuse une deuxième réservation active, quel que soit son statut', function (string $etat, string $autre) {
        $creneau = Creneau::factory()->create();
        Reservation::factory()->{$etat}()->create(['creneau_id' => $creneau->id]);

        expect(fn () => Reservation::factory()->{$autre}()->create(['creneau_id' => $creneau->id]))->toThrow(QueryException::class);
        expect(Reservation::count())->toBe(1);
    })->with([
        'en attente puis en attente' => ['enAttente', 'enAttente'],
        'acceptée puis en attente' => ['acceptee', 'enAttente'],
        'confirmée puis acceptée' => ['confirmee', 'acceptee'],
        'en cours puis en attente' => ['enCours', 'enAttente'],
        'terminée puis en attente' => ['terminee', 'enAttente'],
    ]);

    it('autorise une nouvelle réservation active après une refusée ou annulée', function (string $etat) {
        $creneau = Creneau::factory()->create();
        Reservation::factory()->{$etat}()->create(['creneau_id' => $creneau->id]);

        Reservation::factory()->enAttente()->create(['creneau_id' => $creneau->id]);

        expect($creneau->reservations()->count())->toBe(2);
    })->with(['refusee', 'annulee']);

    it('autorise plusieurs réservations inactives sur le même créneau', function () {
        $creneau = Creneau::factory()->create();
        Reservation::factory()->refusee()->create(['creneau_id' => $creneau->id]);
        Reservation::factory()->annulee()->create(['creneau_id' => $creneau->id]);
        Reservation::factory()->refusee()->create(['creneau_id' => $creneau->id]);

        expect($creneau->reservations()->count())->toBe(3);
    });

    it('refuse de réactiver une réservation si le créneau a déjà une active', function () {
        $creneau = Creneau::factory()->create();
        $annulee = Reservation::factory()->annulee()->create(['creneau_id' => $creneau->id]);
        Reservation::factory()->enAttente()->create(['creneau_id' => $creneau->id]);

        $annulee->statut = StatutReservation::EnAttente;

        expect(fn () => $annulee->save())->toThrow(QueryException::class);
    });
});

describe('suppression interdite', function () {
    it('empêche de supprimer un créneau, une prestation ou un client ayant une réservation', function (string $cible) {
        $reservation = Reservation::factory()->annulee()->create();

        expect(fn () => $reservation->{$cible}->delete())->toThrow(QueryException::class);
        expect(Reservation::count())->toBe(1);
    })->with(['creneau', 'prestation', 'client']);
});

describe('enum StatutReservation', function () {
    it('a les sept statuts attendus', function () {
        expect(array_map(fn ($s) => $s->value, StatutReservation::cases()))
            ->toBe(['en_attente', 'acceptee', 'confirmee', 'en_cours', 'terminee', 'refusee', 'annulee']);
    });

    it('est actif sauf pour refusee et annulee', function () {
        foreach (StatutReservation::cases() as $statut) {
            expect($statut->estActif())->toBe(! in_array($statut->value, ['refusee', 'annulee'], true));
        }
    });

    it('est cohérent avec l\'index unique partiel', function () {
        foreach (StatutReservation::cases() as $statut) {
            $creneau = Creneau::factory()->create();
            $premiere = Reservation::factory()->create(['creneau_id' => $creneau->id]);
            $premiere->forceFill(['statut' => $statut])->save();

            $accepte = true;
            try {
                Reservation::factory()->create(['creneau_id' => $creneau->id]);
            } catch (QueryException) {
                $accepte = false;
            }

            expect($accepte)->toBe(! $statut->estActif());
        }
    });
});

describe('factory', function () {
    it('crée une réservation par défaut en attente, avec une prestation du même prestataire', function () {
        $reservation = Reservation::factory()->create();

        expect($reservation->statut)->toBe(StatutReservation::EnAttente)
            ->and($reservation->prestation->prestataire_id)->toBe($reservation->creneau->prestataire_id)
            ->and($reservation->acceptee_le)->toBeNull()
            ->and($reservation->code_reservation)->toBeNull();
    });

    it('produit des données cohérentes pour chaque état', function (string $etat, StatutReservation $statut, bool $acceptee, bool $code, bool $motif) {
        $reservation = Reservation::factory()->{$etat}()->create()->fresh();

        expect($reservation->statut)->toBe($statut)
            ->and($reservation->acceptee_le !== null)->toBe($acceptee)
            ->and($reservation->motif_refus !== null)->toBe($motif);
        if ($code) {
            expect($reservation->code_reservation)->toMatch('/^[A-Z0-9]{6}$/');
        } else {
            expect($reservation->code_reservation)->toBeNull();
        }
    })->with([
        'enAttente' => ['enAttente', StatutReservation::EnAttente, false, false, false],
        'acceptee' => ['acceptee', StatutReservation::Acceptee, true, false, false],
        'confirmee' => ['confirmee', StatutReservation::Confirmee, true, true, false],
        'enCours' => ['enCours', StatutReservation::EnCours, true, true, false],
        'terminee' => ['terminee', StatutReservation::Terminee, true, true, false],
        'refusee' => ['refusee', StatutReservation::Refusee, false, false, true],
        'annulee' => ['annulee', StatutReservation::Annulee, false, false, false],
    ]);

    it('accepte un motif de refus personnalisé', function () {
        expect(Reservation::factory()->refusee('Indisponible')->create()->motif_refus)->toBe('Indisponible');
    });
});
