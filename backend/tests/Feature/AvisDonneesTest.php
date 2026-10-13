<?php

use App\Enums\StatutReservation;
use App\Models\Avis;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('table avis', function () {
    it('a les colonnes prévues', function () {
        expect(Schema::getColumnListing('avis'))->toEqualCanonicalizing([
            'id', 'reservation_id', 'client_id', 'prestataire_id', 'note', 'commentaire', 'created_at', 'updated_at',
        ]);
    });

    it('a les index prévus, dont l\'unicité par réservation', function () {
        $index = collect(Schema::getIndexes('avis'));
        $colonnes = $index->map(fn ($i) => implode(',', $i['columns']))->all();

        expect($colonnes)->toContain('prestataire_id,created_at', 'client_id', 'reservation_id')
            ->and($index->firstWhere('name', 'avis_reservation_unique')['unique'])->toBeTrue()
            ->and($index->pluck('name')->all())->toContain('avis_prestataire_date_index', 'avis_client_index');
    });

    it('crée un avis via la factory : UUID, note entière, commentaire vide, client et prestataire de la réservation', function () {
        $avis = Avis::factory()->create()->fresh();

        expect($avis->id)->toBeUuid()
            ->and($avis->note)->toBe(4)
            ->and($avis->commentaire)->toBeNull()
            ->and($avis->created_at)->not->toBeNull()
            ->and($avis->reservation->statut)->toBe(StatutReservation::Terminee)
            ->and($avis->client_id)->toBe($avis->reservation->client_id)
            ->and($avis->prestataire_id)->toBe($avis->reservation->creneau->prestataire_id);
    });

    it('n\'est pas assignable en masse', function () {
        expect((new Avis)->getFillable())->toBe([])
            ->and(fn () => Avis::query()->create(['note' => 5]))->toThrow(MassAssignmentException::class);
    });
});

describe('contraintes sur la note', function () {
    it('accepte 1 à 5', function (int $note) {
        Avis::factory()->note($note)->create();

        expect(Avis::query()->value('note'))->toBe($note);
    })->with([1, 2, 3, 4, 5]);

    it('refuse 0, 6 et une note négative à l\'insertion', function (int $note) {
        expect(fn () => Avis::factory()->note($note)->create())->toThrow(QueryException::class);
        expect(Avis::count())->toBe(0);
    })->with([0, 6, 255, -1]);

    it('refuse une note invalide à la mise à jour', function () {
        $avis = Avis::factory()->note(3)->create();

        expect(fn () => DB::table('avis')->where('id', $avis->id)->update(['note' => 6]))->toThrow(QueryException::class);
        expect(fn () => DB::table('avis')->where('id', $avis->id)->update(['note' => 0]))->toThrow(QueryException::class);
        expect($avis->fresh()->note)->toBe(3);
    });

    it('accepte un commentaire long', function () {
        Avis::factory()->commente(str_repeat('é', 1000))->create();

        expect(mb_strlen(Avis::query()->value('commentaire')))->toBe(1000);
    });
});

describe('unicité et clés étrangères', function () {
    it('refuse un second avis pour la même réservation', function () {
        $premier = Avis::factory()->create();

        expect(fn () => Avis::factory()->create(['reservation_id' => $premier->reservation_id]))->toThrow(QueryException::class);
        expect(Avis::count())->toBe(1);
    });

    it('accepte des avis pour des réservations différentes du même client et du même prestataire', function () {
        $premier = Avis::factory()->create();
        $reservation = Reservation::factory()->terminee()->create(['client_id' => $premier->client_id, 'creneau_id' => Creneau::factory()->for($premier->prestataire)->create()->id]);
        Avis::factory()->create(['reservation_id' => $reservation->id]);

        expect(Avis::count())->toBe(2)
            ->and($premier->prestataire->avis)->toHaveCount(2)
            ->and(User::find($premier->client_id)->avisDonnes)->toHaveCount(2);
    });

    it('refuse une clé étrangère inconnue', function (string $colonne) {
        $reservation = Reservation::factory()->terminee()->create();
        $valides = [
            'reservation_id' => $reservation->id,
            'client_id' => $reservation->client_id,
            'prestataire_id' => $reservation->creneau->prestataire_id,
        ];
        $inconnu = [$colonne => (string) Str::uuid()] + $valides;

        expect(fn () => Avis::factory()->create($inconnu))->toThrow(QueryException::class);
        expect(Avis::count())->toBe(0);
    })->with(['reservation_id', 'client_id', 'prestataire_id']);

    it('empêche de supprimer la réservation, le client ou le prestataire d\'un avis', function () {
        $avis = Avis::factory()->create();

        expect(fn () => DB::table('reservations')->where('id', $avis->reservation_id)->delete())->toThrow(QueryException::class);
        expect(fn () => DB::table('users')->where('id', $avis->client_id)->delete())->toThrow(QueryException::class);
        expect(fn () => DB::table('prestataires')->where('id', $avis->prestataire_id)->delete())->toThrow(QueryException::class);
    });
});

describe('relations', function () {
    it('relie l\'avis à sa réservation, son client et son prestataire, et la réservation à son avis', function () {
        $avis = Avis::factory()->commente('Très bien')->create();

        expect($avis->reservation->avis->id)->toBe($avis->id)
            ->and($avis->client)->toBeInstanceOf(User::class)
            ->and($avis->prestataire)->toBeInstanceOf(Prestataire::class)
            ->and($avis->commentaire)->toBe('Très bien');
    });

    it('n\'a pas d\'avis tant que personne n\'a noté', function () {
        $reservation = Reservation::factory()->terminee()->create();

        expect($reservation->avis)->toBeNull();
    });
});
