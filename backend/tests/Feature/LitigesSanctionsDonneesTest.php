<?php

use App\Enums\DecisionLitige;
use App\Enums\StatutCompte;
use App\Enums\StatutLitige;
use App\Enums\TypeSanction;
use App\Models\Avis;
use App\Models\Litige;
use App\Models\LitigePreuve;
use App\Models\Reservation;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Index d'une table, par nom. */
function indexLitiges(string $table): Collection
{
    return collect(Schema::getIndexes($table))->keyBy('name');
}

describe('table litiges', function () {
    it('a les colonnes et les index prévus', function () {
        expect(Schema::getColumnListing('litiges'))->toEqualCanonicalizing([
            'id', 'reservation_id', 'client_id', 'prestataire_id', 'statut', 'description', 'reponse_prestataire', 'reponse_le',
            'decision', 'motif_decision', 'decide_par_id', 'cloture_le', 'created_at', 'updated_at',
        ]);

        $index = indexLitiges('litiges');
        expect($index->keys()->all())->toContain(
            'litiges_statut_date_index', 'litiges_client_date_index', 'litiges_prestataire_date_index', 'litiges_reservation_index', 'litiges_un_ouvert_par_reservation',
        )->and($index['litiges_un_ouvert_par_reservation']['unique'])->toBeTrue();
    });

    it('crée un litige ouvert via la factory, avec le client et le prestataire de la réservation', function () {
        $litige = Litige::factory()->create()->fresh();

        expect($litige->id)->toBeUuid()
            ->and($litige->statut)->toBe(StatutLitige::Ouvert)
            ->and($litige->decision)->toBeNull()
            ->and($litige->reponse_prestataire)->toBeNull()
            ->and($litige->client_id)->toBe($litige->reservation->client_id)
            ->and($litige->prestataire_id)->toBe($litige->reservation->creneau->prestataire_id)
            ->and($litige->client)->toBeInstanceOf(User::class);
    });

    it('n\'est pas assignable en masse', function () {
        expect((new Litige)->getFillable())->toBe([])
            ->and(fn () => Litige::query()->create(['description' => 'x']))->toThrow(MassAssignmentException::class);
    });

    it('relie la réservation, le client et le prestataire à leurs litiges', function () {
        $litige = Litige::factory()->create();

        expect($litige->reservation->litiges)->toHaveCount(1)
            ->and($litige->client->litiges)->toHaveCount(1)
            ->and($litige->prestataire->litiges)->toHaveCount(1);
    });

    it('refuse deux litiges ouverts sur la même réservation, mais accepte un nouveau litige après clôture', function () {
        $premier = Litige::factory()->create();

        expect(fn () => Litige::factory()->create(['reservation_id' => $premier->reservation_id]))->toThrow(QueryException::class);

        DB::table('litiges')->where('id', $premier->id)->update([
            'statut' => 'cloture', 'decision' => 'rejet', 'motif_decision' => 'Non fondé.', 'decide_par_id' => User::factory()->administrateur()->create()->id, 'cloture_le' => now(),
        ]);
        Litige::factory()->create(['reservation_id' => $premier->reservation_id]);

        expect(Litige::where('reservation_id', $premier->reservation_id)->count())->toBe(2);
    });

    it('accepte des litiges ouverts sur des réservations différentes', function () {
        Litige::factory()->count(2)->create();

        expect(Litige::count())->toBe(2);
    });

    it('refuse un statut inconnu', function () {
        $litige = Litige::factory()->create();

        // Valeur brute : le cast de l'énumération refuserait déjà une valeur inconnue avant la base.
        expect(fn () => DB::table('litiges')->where('id', $litige->id)->update(['statut' => 'en_examen']))->toThrow(QueryException::class);
    });

    it('refuse une décision sur un litige ouvert, et un litige clôturé sans décision, sans motif ou sans auteur', function (array $anomalie) {
        expect(fn () => Litige::factory()->create($anomalie))->toThrow(QueryException::class);
        expect(Litige::count())->toBe(0);
    })->with([
        'décision sur un ouvert' => [['decision' => DecisionLitige::Rejet]],
        'clôture sur un ouvert' => [['cloture_le' => now()]],
        'clos sans décision' => [['statut' => StatutLitige::Cloture, 'motif_decision' => 'x', 'cloture_le' => now()]],
        'clos sans motif' => [['statut' => StatutLitige::Cloture, 'decision' => DecisionLitige::Rejet, 'cloture_le' => now()]],
        'clos sans date' => [['statut' => StatutLitige::Cloture, 'decision' => DecisionLitige::Rejet, 'motif_decision' => 'x']],
        'réponse sans date' => [['reponse_prestataire' => 'Ma version.']],
    ]);

    it('refuse une décision inconnue sur un litige clôturé', function () {
        $litige = Litige::factory()->cloture()->create();

        expect(fn () => DB::table('litiges')->where('id', $litige->id)->update(['decision' => 'remboursement']))->toThrow(QueryException::class);
        expect($litige->fresh()->decision)->toBe(DecisionLitige::Rejet);
    });

    it('accepte un litige clôturé avec chacune des quatre décisions', function (DecisionLitige $decision) {
        $litige = Litige::factory()->cloture($decision)->create()->fresh();

        expect($litige->statut)->toBe(StatutLitige::Cloture)
            ->and($litige->decision)->toBe($decision)
            ->and($litige->decidePar)->toBeInstanceOf(User::class)
            ->and($litige->cloture_le)->not->toBeNull();
    })->with(DecisionLitige::cases());

    it('accepte une réponse du prestataire avec sa date', function () {
        $litige = Litige::factory()->repondu('Ma version des faits.')->create()->fresh();

        expect($litige->reponse_prestataire)->toBe('Ma version des faits.')->and($litige->reponse_le)->not->toBeNull();
    });

    it('refuse une clé étrangère inconnue', function (string $colonne) {
        $reservation = Reservation::factory()->confirmee()->create();
        $valides = ['reservation_id' => $reservation->id, 'client_id' => $reservation->client_id, 'prestataire_id' => $reservation->creneau->prestataire_id];

        expect(fn () => Litige::factory()->create([$colonne => (string) Str::uuid()] + $valides))->toThrow(QueryException::class);
    })->with(['reservation_id', 'client_id', 'prestataire_id']);

    it('empêche de supprimer la réservation, le client ou le prestataire d\'un litige', function () {
        $litige = Litige::factory()->create();

        expect(fn () => DB::table('reservations')->where('id', $litige->reservation_id)->delete())->toThrow(QueryException::class);
        expect(fn () => DB::table('users')->where('id', $litige->client_id)->delete())->toThrow(QueryException::class);
        expect(fn () => DB::table('prestataires')->where('id', $litige->prestataire_id)->delete())->toThrow(QueryException::class);
    });
});

describe('table litige_preuves', function () {
    it('a les colonnes et les index prévus', function () {
        expect(Schema::getColumnListing('litige_preuves'))->toEqualCanonicalizing(['id', 'litige_id', 'fichier', 'created_at', 'updated_at']);
        $index = indexLitiges('litige_preuves');

        expect($index->keys()->all())->toContain('litige_preuves_fichier_unique', 'litige_preuves_litige_index')
            ->and($index['litige_preuves_fichier_unique']['unique'])->toBeTrue();
    });

    it('rattache des preuves à un litige et refuse deux fois le même fichier', function () {
        $litige = Litige::factory()->create();
        $preuve = LitigePreuve::factory()->create(['litige_id' => $litige->id, 'fichier' => 'a.jpg']);

        expect($litige->preuves)->toHaveCount(1)
            ->and($preuve->litige->id)->toBe($litige->id)
            ->and((new LitigePreuve)->getFillable())->toBe([])
            ->and(fn () => LitigePreuve::factory()->create(['litige_id' => $litige->id, 'fichier' => 'a.jpg']))->toThrow(QueryException::class);
    });

    it('empêche de supprimer un litige qui a des preuves', function () {
        $preuve = LitigePreuve::factory()->create();

        expect(fn () => DB::table('litiges')->where('id', $preuve->litige_id)->delete())->toThrow(QueryException::class);
    });
});

describe('table sanctions', function () {
    it('a les colonnes et les index prévus, dont les index uniques', function () {
        expect(Schema::getColumnListing('sanctions'))->toEqualCanonicalizing([
            'id', 'user_id', 'type', 'motif', 'date_fin', 'litige_id', 'avis_id', 'pose_par_id', 'levee_le', 'levee_par_id', 'created_at', 'updated_at',
        ]);

        $index = indexLitiges('sanctions');
        expect($index->keys()->all())->toContain('sanctions_user_date_index', 'sanctions_echeance_index', 'sanctions_avis_index', 'sanctions_litige_unique', 'sanctions_un_blocage_actif_par_compte')
            ->and($index['sanctions_litige_unique']['unique'])->toBeTrue()
            ->and($index['sanctions_un_blocage_actif_par_compte']['unique'])->toBeTrue();
    });

    it('crée un avertissement via la factory', function () {
        $sanction = Sanction::factory()->create()->fresh();

        expect($sanction->id)->toBeUuid()
            ->and($sanction->type)->toBe(TypeSanction::Avertissement)
            ->and($sanction->date_fin)->toBeNull()
            ->and($sanction->levee_le)->toBeNull()
            ->and($sanction->user)->toBeInstanceOf(User::class)
            ->and($sanction->posePar)->toBeInstanceOf(User::class)
            ->and($sanction->user->sanctions)->toHaveCount(1)
            ->and((new Sanction)->getFillable())->toBe([]);
    });

    it('exige une date de fin pour une suspension, et l\'interdit pour les autres types', function () {
        expect(fn () => Sanction::factory()->create(['type' => TypeSanction::SuspensionTemporaire, 'date_fin' => null]))->toThrow(QueryException::class);
        expect(fn () => Sanction::factory()->create(['type' => TypeSanction::Avertissement, 'date_fin' => now()->addDay()]))->toThrow(QueryException::class);
        expect(fn () => Sanction::factory()->bannissement()->create(['date_fin' => now()->addDay()]))->toThrow(QueryException::class);

        Sanction::factory()->suspension()->create();
        Sanction::factory()->bannissement()->create();
        expect(Sanction::count())->toBe(2);
    });

    it('refuse un type inconnu et une levée par un administrateur sans date', function () {
        $sanction = Sanction::factory()->create();
        expect(fn () => DB::table('sanctions')->where('id', $sanction->id)->update(['type' => 'expulsion']))->toThrow(QueryException::class);
        expect(fn () => Sanction::factory()->create(['levee_par_id' => User::factory()->administrateur()]))->toThrow(QueryException::class);
        expect(Sanction::count())->toBe(1);
    });

    it('accepte une levée automatique (date sans administrateur) et une levée par un administrateur', function () {
        Sanction::factory()->suspension()->create(['levee_le' => now()]);
        Sanction::factory()->levee()->create();

        expect(Sanction::count())->toBe(2)->and(Sanction::whereNotNull('levee_par_id')->count())->toBe(1);
    });

    it('refuse deux sanctions de blocage actives sur le même compte, mais accepte les autres cas', function () {
        $compte = User::factory()->prestataire()->create();
        Sanction::factory()->suspension()->create(['user_id' => $compte->id]);

        // Un second blocage actif : refusé, quel que soit le type.
        expect(fn () => Sanction::factory()->suspension()->create(['user_id' => $compte->id]))->toThrow(QueryException::class);
        expect(fn () => Sanction::factory()->bannissement()->create(['user_id' => $compte->id]))->toThrow(QueryException::class);

        // Un avertissement ne bloque rien : autant que l'on veut. Un autre compte n'est pas concerné.
        Sanction::factory()->count(2)->create(['user_id' => $compte->id]);
        Sanction::factory()->suspension()->create();

        // Une fois levée, la sanction n'empêche plus un nouveau blocage (l'historique est conservé).
        Sanction::bloquantesActives()->where('user_id', $compte->id)->update(['levee_le' => now()]);
        Sanction::factory()->bannissement()->create(['user_id' => $compte->id]);

        expect($compte->sanctions()->count())->toBe(4)
            ->and(Sanction::bloquantesActives()->where('user_id', $compte->id)->count())->toBe(1);
    });

    it('lie au plus une sanction à un litige, et plusieurs sanctions sans litige', function () {
        $litige = Litige::factory()->cloture(DecisionLitige::Avertissement)->create();
        Sanction::factory()->create(['litige_id' => $litige->id]);

        expect(fn () => Sanction::factory()->create(['litige_id' => $litige->id]))->toThrow(QueryException::class);

        Sanction::factory()->count(2)->create();
        expect(Sanction::count())->toBe(3)->and($litige->sanction->litige_id)->toBe($litige->id);
    });

    it('peut viser un avis', function () {
        $avis = Avis::factory()->create();
        $sanction = Sanction::factory()->create(['avis_id' => $avis->id]);

        expect($sanction->avis->id)->toBe($avis->id);
    });

    it('refuse une clé étrangère inconnue et empêche la suppression des comptes et litiges concernés', function () {
        foreach (['user_id', 'pose_par_id', 'litige_id', 'avis_id', 'levee_par_id'] as $colonne) {
            expect(fn () => Sanction::factory()->create([$colonne => (string) Str::uuid()]))->toThrow(QueryException::class);
        }

        $sanction = Sanction::factory()->create();
        expect(fn () => DB::table('users')->where('id', $sanction->user_id)->delete())->toThrow(QueryException::class);
        expect(fn () => DB::table('users')->where('id', $sanction->pose_par_id)->delete())->toThrow(QueryException::class);
        expect(Sanction::count())->toBe(1);
    });
});

describe('énumérations', function () {
    it('associe chaque décision à son type de sanction, un rejet n\'en a aucun', function () {
        expect(DecisionLitige::Rejet->typeSanction())->toBeNull()
            ->and(DecisionLitige::Avertissement->typeSanction())->toBe(TypeSanction::Avertissement)
            ->and(DecisionLitige::SuspensionTemporaire->typeSanction())->toBe(TypeSanction::SuspensionTemporaire)
            ->and(DecisionLitige::BannissementDefinitif->typeSanction())->toBe(TypeSanction::BannissementDefinitif);
    });

    it('associe les types bloquants au statut du compte', function () {
        expect(TypeSanction::Avertissement->bloque())->toBeFalse()
            ->and(TypeSanction::Avertissement->statutCompte())->toBeNull()
            ->and(TypeSanction::SuspensionTemporaire->bloque())->toBeTrue()
            ->and(TypeSanction::SuspensionTemporaire->statutCompte())->toBe(StatutCompte::Suspendu)
            ->and(TypeSanction::BannissementDefinitif->statutCompte())->toBe(StatutCompte::Banni);
    });
});

describe('modération des avis (colonnes)', function () {
    it('a les index de modération', function () {
        expect(indexLitiges('avis')->keys()->all())->toContain('avis_signales_index', 'avis_visibilite_index', 'avis_reservation_unique');
    });

    it('crée un avis visible, non signalé et non modéré par défaut', function () {
        $avis = Avis::factory()->create()->fresh();

        expect($avis->signale_le)->toBeNull()->and($avis->modere_le)->toBeNull()->and($avis->masque_le)->toBeNull();
    });

    it('accepte un avis signalé, conservé ou masqué par un administrateur', function () {
        $admin = User::factory()->administrateur()->create();
        $signale = Avis::factory()->create();
        DB::table('avis')->where('id', $signale->id)->update(['signale_le' => now(), 'motif_signalement' => 'Propos mensongers.']);

        $masque = Avis::factory()->create();
        DB::table('avis')->where('id', $masque->id)->update([
            'signale_le' => now(), 'motif_signalement' => 'Injurieux.', 'modere_le' => now(), 'modere_par_id' => $admin->id, 'masque_le' => now(), 'motif_moderation' => 'Contenu injurieux.',
        ]);

        expect($signale->fresh()->signale_le)->not->toBeNull()
            ->and($masque->fresh()->moderePar->id)->toBe($admin->id)
            ->and($masque->fresh()->masque_le)->not->toBeNull();
    });

    it('refuse une modération incohérente', function (array $anomalie) {
        $avis = Avis::factory()->create();

        expect(fn () => DB::table('avis')->where('id', $avis->id)->update($anomalie))->toThrow(QueryException::class);
        expect($avis->fresh()->signale_le)->toBeNull();
    })->with([
        'signalement sans motif' => [['signale_le' => '2030-01-01 10:00:00']],
        'motif sans date' => [['motif_signalement' => 'x']],
        'modération sans administrateur' => [['modere_le' => '2030-01-01 10:00:00']],
        'masqué sans modération' => [['masque_le' => '2030-01-01 10:00:00']],
    ]);

    it('conserve la contrainte de la note après la migration de modération', function () {
        $avis = Avis::factory()->create();

        expect(fn () => DB::table('avis')->where('id', $avis->id)->update(['note' => 9]))->toThrow(QueryException::class);
    });

    it('empêche de supprimer l\'administrateur qui a modéré un avis', function () {
        $admin = User::factory()->administrateur()->create();
        $avis = Avis::factory()->create();
        DB::table('avis')->where('id', $avis->id)->update(['signale_le' => now(), 'motif_signalement' => 'x', 'modere_le' => now(), 'modere_par_id' => $admin->id]);

        expect(fn () => DB::table('users')->where('id', $admin->id)->delete())->toThrow(QueryException::class);
    });
});
