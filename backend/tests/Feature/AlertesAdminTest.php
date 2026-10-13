<?php

use App\Enums\StatutCompte;
use App\Enums\StatutEnvoi;
use App\Models\Avis;
use App\Models\Litige;
use App\Models\Notification;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Retrait;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const URL_ALERTES = '/api/v1/admin/alertes';

function alertes(): array
{
    return test()->actingAs(User::factory()->administrateur()->create(), 'sanctum')->getJson(URL_ALERTES)->assertOk()->json();
}

/** L'alerte d'un type, ou null si elle n'est pas levée. */
function alerte(string $type): ?array
{
    return collect(alertes()['alertes'])->firstWhere('type', $type);
}

/** Change l'état d'une ligne directement en base : les colonnes d'état ne sont pas assignables en masse. */
function majBase(string $table, string $id, array $valeurs): void
{
    DB::table($table)->where('id', $id)->update($valeurs);
}

function dossierEnAttente(Carbon $depuis): Prestataire
{
    return Prestataire::factory()->for(User::factory()->prestataire()->statut(StatutCompte::EnAttenteValidation))->create(['created_at' => $depuis]);
}

function avisSignale(Carbon $depuis, array $surcharge = []): Avis
{
    return Avis::factory()->create(['signale_le' => $depuis, 'motif_signalement' => 'Propos déplacés.'] + $surcharge);
}

function remboursementDu(Carbon $depuis, int $tentatives = 0): Paiement
{
    return Paiement::factory()->reussi()->create(['remboursement_du_le' => $depuis, 'remboursement_tentatives' => $tentatives]);
}

beforeEach(fn () => Carbon::setTestNow('2030-01-20 12:00:00'));

describe('alertes — accès', function () {
    it('exige une authentification', function () {
        $this->getJson(URL_ALERTES)->assertUnauthorized();
    });

    it('refuse un client et un prestataire (403)', function () {
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson(URL_ALERTES)->assertForbidden();
        $this->actingAs(User::factory()->prestataire()->create(), 'sanctum')->getJson(URL_ALERTES)->assertForbidden();
    });
});

describe('alertes — liste vide et structure', function () {
    it('renvoie une liste vide quand tout est calme', function () {
        // Éléments qui ne doivent déclencher aucune alerte.
        Prestataire::factory()->create();
        Litige::factory()->cloture()->create();
        Avis::factory()->create();
        Retrait::factory()->reussi()->create();
        Paiement::factory()->rembourse()->create();
        Notification::factory()->create();
        Sanction::factory()->create();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')->getJson(URL_ALERTES)->assertOk()->assertExactJson([
            'dateGeneration' => '2030-01-20T12:00:00+00:00',
            'total' => 0,
            'parGravite' => ['critique' => 0, 'attention' => 0, 'info' => 0],
            'alertes' => [],
        ]);
    });

    it('décrit chaque alerte : type, gravité, compteur, message français, identifiants, âge', function () {
        $litige = Litige::factory()->create(['created_at' => now()->subHours(30)]);

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')->getJson(URL_ALERTES)->assertOk()->assertExactJson([
            'dateGeneration' => '2030-01-20T12:00:00+00:00',
            'total' => 1,
            'parGravite' => ['critique' => 0, 'attention' => 1, 'info' => 0],
            'alertes' => [[
                'type' => 'litiges_ouverts',
                'gravite' => 'attention',
                'compteur' => 1,
                'message' => '1 litige ouvert est à traiter.',
                'ids' => [$litige->id],
                'ageMinutes' => 1800,
                'echeanceDansMinutes' => null,
            ]],
        ]);
    });
});

describe('alertes — chaque type se déclenche puis disparaît', function () {
    it('dossiers de prestataires en attente de validation', function () {
        $dossier = dossierEnAttente(now()->subHour());
        Prestataire::factory()->create(); // compte actif : pas d'alerte

        expect(alerte('dossiers_en_attente'))->toMatchArray(['compteur' => 1, 'ids' => [$dossier->id], 'ageMinutes' => 60]);

        majBase('users', $dossier->user_id, ['statut' => StatutCompte::Actif->value]);
        expect(alerte('dossiers_en_attente'))->toBeNull();
    });

    it('litiges ouverts', function () {
        $litige = Litige::factory()->create();
        expect(alerte('litiges_ouverts'))->toMatchArray(['compteur' => 1, 'ids' => [$litige->id]]);

        majBase('litiges', $litige->id, ['statut' => 'cloture', 'decision' => 'rejet', 'motif_decision' => 'Signalement non fondé.', 'decide_par_id' => User::factory()->administrateur()->create()->id, 'cloture_le' => now()]);
        expect(alerte('litiges_ouverts'))->toBeNull();
    });

    it('avis signalés à modérer (ni modérés ni masqués)', function () {
        $avis = avisSignale(now()->subMinutes(10));
        expect(alerte('avis_signales'))->toMatchArray(['compteur' => 1, 'ids' => [$avis->id], 'ageMinutes' => 10]);

        majBase('avis', $avis->id, ['modere_le' => now(), 'modere_par_id' => User::factory()->administrateur()->create()->id, 'motif_moderation' => 'Avis conforme.']);
        expect(alerte('avis_signales'))->toBeNull();

        avisSignale(now(), ['modere_le' => now(), 'modere_par_id' => User::factory()->administrateur()->create()->id, 'motif_moderation' => 'Masqué.', 'masque_le' => now()]);
        expect(alerte('avis_signales'))->toBeNull();
    });

    it('retraits EN_COURS au-delà du délai de blocage', function () {
        config(['sopi.retraits.delai_blocage_minutes' => 15]);
        Retrait::factory()->enCours()->create(['created_at' => now()->subMinutes(14)]);
        expect(alerte('retraits_bloques'))->toBeNull();

        $bloque = Retrait::factory()->enCours()->create(['created_at' => now()->subMinutes(15)]);
        Retrait::factory()->reussi()->create(['created_at' => now()->subDay(), 'traite_le' => now()]);
        expect(alerte('retraits_bloques'))->toMatchArray(['compteur' => 1, 'ids' => [$bloque->id], 'gravite' => 'attention']);

        majBase('retraits', $bloque->id, ['statut' => 'reussi', 'traite_le' => now()]);
        expect(alerte('retraits_bloques'))->toBeNull();
    });

    it('remboursements en échec (nombre maximal d\'essais atteint)', function () {
        config(['sopi.paiement.remboursement_max_tentatives' => 10]);
        remboursementDu(now()->subMinutes(2), 9);
        expect(alerte('remboursements_en_echec'))->toBeNull();

        $echec = remboursementDu(now()->subHour(), 10);
        expect(alerte('remboursements_en_echec'))->toMatchArray(['compteur' => 1, 'ids' => [$echec->id], 'gravite' => 'critique']);

        majBase('paiements', $echec->id, ['statut' => 'rembourse', 'rembourse_le' => now()]);
        expect(alerte('remboursements_en_echec'))->toBeNull();
    });

    it('remboursements en retard (encore en reprise automatique)', function () {
        config(['sopi.admin.alertes.remboursements_retard_minutes' => 30, 'sopi.paiement.remboursement_max_tentatives' => 10]);
        remboursementDu(now()->subMinutes(29), 1);
        Paiement::factory()->reussi()->create(); // aucun remboursement dû
        expect(alerte('remboursements_en_retard'))->toBeNull();

        $retard = remboursementDu(now()->subMinutes(30), 3);
        remboursementDu(now()->subHours(3), 10); // en échec, pas « en retard »
        expect(alerte('remboursements_en_retard'))->toMatchArray(['compteur' => 1, 'ids' => [$retard->id], 'gravite' => 'attention', 'ageMinutes' => 30]);
    });

    it('notifications en échec définitif sur au moins un canal', function () {
        Notification::factory()->create(['canal_email_statut' => StatutEnvoi::EnAttente, 'canal_push_statut' => StatutEnvoi::NonApplicable]);
        expect(alerte('notifications_en_echec'))->toBeNull();

        $email = Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue]);
        $push = Notification::factory()->create(['canal_push_statut' => StatutEnvoi::Echoue, 'created_at' => now()->addMinute()]);
        expect(alerte('notifications_en_echec'))->toMatchArray(['compteur' => 2, 'gravite' => 'attention'])
            ->and(alerte('notifications_en_echec')['ids'])->toEqualCanonicalizing([$email->id, $push->id]);

        Notification::query()->update(['canal_email_statut' => StatutEnvoi::Envoye, 'canal_push_statut' => StatutEnvoi::Envoye]);
        expect(alerte('notifications_en_echec'))->toBeNull();
    });

    it('suspensions temporaires qui prennent fin dans les 24 heures', function () {
        config(['sopi.admin.alertes.suspension_expiration_heures' => 24]);
        Sanction::factory()->suspension(now()->addHours(25))->create();       // trop loin
        Sanction::factory()->suspension(now()->subMinute())->create();         // déjà terminée
        Sanction::factory()->suspension(now()->addHours(2))->levee()->create(); // levée
        Sanction::factory()->bannissement()->create();                          // pas une suspension
        expect(alerte('suspensions_a_echeance'))->toBeNull();

        $proche = Sanction::factory()->suspension(now()->addHours(10))->create();
        expect(alerte('suspensions_a_echeance'))->toMatchArray(['compteur' => 1, 'ids' => [$proche->id], 'gravite' => 'info', 'echeanceDansMinutes' => 600, 'ageMinutes' => null]);

        majBase('sanctions', $proche->id, ['levee_le' => now(), 'levee_par_id' => User::factory()->administrateur()->create()->id]);
        expect(alerte('suspensions_a_echeance'))->toBeNull();
    });
});

describe('alertes — seuils de gravité', function () {
    it('dossiers, litiges et avis : info, attention à 24 h, critique à 72 h (bornes incluses)', function (int $minutes, string $gravite) {
        $depuis = now()->subMinutes($minutes);
        dossierEnAttente($depuis);
        Litige::factory()->create(['created_at' => $depuis]);
        avisSignale($depuis);

        foreach (['dossiers_en_attente', 'litiges_ouverts', 'avis_signales'] as $type) {
            expect(alerte($type)['gravite'])->toBe($gravite);
        }
    })->with([
        'récent' => [5, 'info'],
        'juste avant 24 h' => [24 * 60 - 1, 'info'],
        '24 h' => [24 * 60, 'attention'],
        'juste avant 72 h' => [72 * 60 - 1, 'attention'],
        '72 h' => [72 * 60, 'critique'],
    ]);

    it('respecte les seuils configurés', function () {
        config(['sopi.admin.alertes.age_attention_heures' => 1, 'sopi.admin.alertes.age_critique_heures' => 2]);
        Litige::factory()->create(['created_at' => now()->subMinutes(90)]);
        expect(alerte('litiges_ouverts')['gravite'])->toBe('attention');

        config(['sopi.admin.alertes.age_critique_heures' => 1]);
        expect(alerte('litiges_ouverts')['gravite'])->toBe('critique');
    });

    it('retraits bloqués : critique à partir de 60 minutes', function () {
        Retrait::factory()->enCours()->create(['created_at' => now()->subMinutes(59)]);
        expect(alerte('retraits_bloques')['gravite'])->toBe('attention');

        Retrait::factory()->enCours()->create(['created_at' => now()->subMinutes(60)]);
        expect(alerte('retraits_bloques'))->toMatchArray(['gravite' => 'critique', 'compteur' => 2, 'ageMinutes' => 60]);
    });

    it('notifications en échec : critique à partir de 20', function () {
        Notification::factory()->count(19)->create(['canal_email_statut' => StatutEnvoi::Echoue]);
        expect(alerte('notifications_en_echec')['gravite'])->toBe('attention');

        Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue]);
        expect(alerte('notifications_en_echec'))->toMatchArray(['gravite' => 'critique', 'compteur' => 20]);
    });
});

describe('alertes — identifiants, ordre et volume', function () {
    it('renvoie les plus anciens d\'abord, limités à ids_max, avec le compteur réel', function () {
        config(['sopi.admin.alertes.ids_max' => 3]);
        $litiges = collect(range(1, 5))->map(fn ($i) => Litige::factory()->create(['created_at' => now()->subHours($i)]));

        $alerte = alerte('litiges_ouverts');

        expect($alerte['compteur'])->toBe(5)
            ->and($alerte['ids'])->toBe([$litiges[4]->id, $litiges[3]->id, $litiges[2]->id])
            ->and($alerte['ageMinutes'])->toBe(300);
    });

    it('trie les alertes de la plus grave à la moins grave', function () {
        Sanction::factory()->suspension(now()->addHours(3))->create();                // info
        Litige::factory()->create(['created_at' => now()->subHours(30)]);              // attention
        remboursementDu(now()->subHours(2), 10);                                       // critique

        $reponse = alertes();

        expect(array_column($reponse['alertes'], 'gravite'))->toBe(['critique', 'attention', 'info'])
            ->and($reponse['parGravite'])->toBe(['critique' => 1, 'attention' => 1, 'info' => 1])
            ->and($reponse['total'])->toBe(3);
    });

    it('exécute un nombre de requêtes constant quel que soit le volume', function () {
        $compter = function () {
            $admin = User::factory()->administrateur()->create();
            DB::flushQueryLog();
            DB::enableQueryLog();
            test()->actingAs($admin, 'sanctum')->getJson(URL_ALERTES)->assertOk();
            $nombre = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $nombre;
        };

        $vide = $compter();
        dossierEnAttente(now()->subDay());
        Litige::factory()->create(['created_at' => now()->subDay()]);
        avisSignale(now()->subDay());
        Retrait::factory()->enCours()->create(['created_at' => now()->subHour()]);
        remboursementDu(now()->subHour(), 10);
        remboursementDu(now()->subHour(), 1);
        Notification::factory()->create(['canal_push_statut' => StatutEnvoi::Echoue]);
        Sanction::factory()->suspension(now()->addHours(2))->create();
        $quelques = $compter();

        foreach (range(1, 5) as $i) {
            dossierEnAttente(now()->subDays($i));
            Litige::factory()->create(['created_at' => now()->subDays($i)]);
            Retrait::factory()->enCours()->create(['created_at' => now()->subHours($i)]);
            Notification::factory()->create(['canal_email_statut' => StatutEnvoi::Echoue]);
        }
        $beaucoup = $compter();

        expect($quelques)->toBe($vide)->and($beaucoup)->toBe($vide);
    });

    it('n\'expose aucune donnée personnelle', function () {
        $retrait = Retrait::factory()->enCours()->create(['created_at' => now()->subHour()]);
        $dossier = dossierEnAttente(now()->subDay());

        $corps = test()->actingAs(User::factory()->administrateur()->create(), 'sanctum')->getJson(URL_ALERTES)->assertOk()->getContent();

        expect($corps)->not->toContain($retrait->telephone_versement)
            ->not->toContain($dossier->user->telephone)
            ->not->toContain($dossier->user->nom)
            ->not->toContain($dossier->nom_entreprise)
            ->not->toContain($dossier->user_id);
    });
});
