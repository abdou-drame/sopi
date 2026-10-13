<?php

use App\Enums\StatutCompte;
use App\Models\Avis;
use App\Models\Litige;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const URL_TABLEAU_DE_BORD = '/api/v1/admin/tableau-de-bord';

function tableauDeBord(): TestResponse
{
    return test()->actingAs(User::factory()->administrateur()->create(), 'sanctum')->getJson(URL_TABLEAU_DE_BORD);
}

/** Nombre de requêtes SQL exécutées par un appel au tableau de bord (hors création de l'administrateur). */
function requetesTableauDeBord(): int
{
    $admin = User::factory()->administrateur()->create();
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->actingAs($admin, 'sanctum')->getJson(URL_TABLEAU_DE_BORD)->assertOk();
    $nombre = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $nombre;
}

describe('tableau de bord — accès', function () {
    it('exige une authentification', function () {
        $this->getJson(URL_TABLEAU_DE_BORD)->assertUnauthorized();
    });

    it('refuse un client et un prestataire (403)', function () {
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson(URL_TABLEAU_DE_BORD)->assertForbidden();
        $this->actingAs(User::factory()->prestataire()->create(), 'sanctum')->getJson(URL_TABLEAU_DE_BORD)->assertForbidden();
    });

    it('est en lecture seule : les autres méthodes n\'existent pas', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')->postJson(URL_TABLEAU_DE_BORD)->assertStatus(405);
    });
});

describe('tableau de bord — structure', function () {
    it('renvoie la structure complète, tout à zéro sur une base vide', function () {
        Carbon::setTestNow('2030-01-20 10:00:00');

        tableauDeBord()->assertOk()->assertExactJson([
            'dateGeneration' => '2030-01-20T10:00:00+00:00',
            'comptes' => [
                'clients' => ['total' => 0, 'parStatut' => ['en_attente' => 0, 'actif' => 0, 'refuse' => 0, 'suspendu' => 0, 'banni' => 0]],
                'prestataires' => [
                    'total' => 0,
                    'parStatut' => ['en_attente' => 0, 'actif' => 0, 'refuse' => 0, 'suspendu' => 0, 'banni' => 0],
                    'dossiersEnAttente' => 0,
                ],
                'administrateurs' => 1,
            ],
            'reservations' => [
                'total' => 0,
                'parStatut' => ['en_attente' => 0, 'acceptee' => 0, 'confirmee' => 0, 'en_cours' => 0, 'terminee' => 0, 'refusee' => 0, 'annulee' => 0],
                'parDomaine' => [
                    'mecanique' => ['total' => 0, 'parStatut' => ['en_attente' => 0, 'acceptee' => 0, 'confirmee' => 0, 'en_cours' => 0, 'terminee' => 0, 'refusee' => 0, 'annulee' => 0]],
                    'coiffure' => ['total' => 0, 'parStatut' => ['en_attente' => 0, 'acceptee' => 0, 'confirmee' => 0, 'en_cours' => 0, 'terminee' => 0, 'refusee' => 0, 'annulee' => 0]],
                    'pressing' => ['total' => 0, 'parStatut' => ['en_attente' => 0, 'acceptee' => 0, 'confirmee' => 0, 'en_cours' => 0, 'terminee' => 0, 'refusee' => 0, 'annulee' => 0]],
                ],
                'activiteRecente' => [
                    'derniers7Jours' => ['creees' => 0, 'terminees' => 0],
                    'derniers30Jours' => ['creees' => 0, 'terminees' => 0],
                ],
            ],
            'paiements' => [
                'total' => ['nombre' => 0, 'montant' => 0],
                'parStatut' => [
                    'en_attente' => ['nombre' => 0, 'montant' => 0],
                    'reussi' => ['nombre' => 0, 'montant' => 0],
                    'echoue' => ['nombre' => 0, 'montant' => 0],
                    'rembourse' => ['nombre' => 0, 'montant' => 0],
                ],
            ],
            'litiges' => ['total' => 0, 'ouverts' => 0, 'clos' => 0],
            'avis' => ['total' => 0, 'signales' => 0, 'masques' => 0, 'noteMoyenne' => null],
            'sanctions' => ['suspensionsEnCours' => 0, 'bannissements' => 0],
        ]);
    });
});

describe('tableau de bord — jeu de données connu', function () {
    beforeEach(fn () => Carbon::setTestNow('2030-01-20 10:00:00'));

    it('compte les comptes par rôle et par statut, et les dossiers en attente', function () {
        User::factory()->count(3)->create();
        User::factory()->statut(StatutCompte::Banni)->create();
        User::factory()->prestataire()->count(2)->create();
        User::factory()->prestataire()->statut(StatutCompte::EnAttenteValidation)->count(4)->create();
        User::factory()->prestataire()->statut(StatutCompte::Refuse)->create();

        $comptes = tableauDeBord()->assertOk()->json('comptes');

        expect($comptes['clients']['total'])->toBe(4)
            ->and($comptes['clients']['parStatut']['actif'])->toBe(3)
            ->and($comptes['clients']['parStatut']['banni'])->toBe(1)
            ->and($comptes['prestataires']['total'])->toBe(7)
            ->and($comptes['prestataires']['parStatut'])->toBe(['en_attente' => 4, 'actif' => 2, 'refuse' => 1, 'suspendu' => 0, 'banni' => 0])
            ->and($comptes['prestataires']['dossiersEnAttente'])->toBe(4)
            ->and($comptes['administrateurs'])->toBe(1);
    });

    it('compte les réservations par statut, par domaine et sur les fenêtres de 7 et 30 jours', function () {
        // Créée et terminée il y a 2 jours : dans les deux fenêtres.
        $recente = Reservation::factory()->terminee()->create(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(2)]);
        // Créée il y a 20 jours, terminée il y a 10 jours : seulement dans la fenêtre de 30 jours.
        Reservation::factory()->terminee()->create(['created_at' => now()->subDays(20), 'updated_at' => now()->subDays(10)]);
        // Créée il y a 40 jours : hors fenêtres.
        Reservation::factory()->terminee()->create(['created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);
        Reservation::factory()->enAttente()->create(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        Reservation::factory()->annulee()->create(['created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)]);

        DB::table('prestations')->where('id', $recente->prestation_id)->update(['domaine' => 'coiffure']);
        DB::table('prestations')->whereIn('id', Reservation::where('statut', 'annulee')->pluck('prestation_id'))->update(['domaine' => 'pressing']);
        DB::table('prestations')->whereNotIn('id', Reservation::whereIn('statut', ['annulee'])->orWhere('id', $recente->id)->pluck('prestation_id'))->update(['domaine' => 'mecanique']);

        $reservations = tableauDeBord()->assertOk()->json('reservations');

        expect($reservations['total'])->toBe(5)
            ->and($reservations['parStatut']['terminee'])->toBe(3)
            ->and($reservations['parStatut']['en_attente'])->toBe(1)
            ->and($reservations['parStatut']['annulee'])->toBe(1)
            ->and($reservations['parDomaine']['coiffure']['total'])->toBe(1)
            ->and($reservations['parDomaine']['coiffure']['parStatut']['terminee'])->toBe(1)
            ->and($reservations['parDomaine']['pressing']['total'])->toBe(1)
            ->and($reservations['parDomaine']['mecanique']['total'])->toBe(3)
            ->and($reservations['activiteRecente']['derniers7Jours'])->toBe(['creees' => 2, 'terminees' => 1])
            ->and($reservations['activiteRecente']['derniers30Jours'])->toBe(['creees' => 4, 'terminees' => 2]);
    });

    it('totalise les paiements par statut en FCFA entiers', function () {
        Paiement::factory()->reussi()->count(2)->create(['montant_acompte' => '4500.00', 'commission' => '1500.00']);
        Paiement::factory()->reussi()->create(['montant_acompte' => '3000.00']);
        Paiement::factory()->enAttente()->create(['montant_acompte' => '6000.00']);
        Paiement::factory()->rembourse()->create(['montant_acompte' => '3000.00']);
        Paiement::factory()->echoue()->create(['montant_acompte' => '3000.00']);

        $paiements = tableauDeBord()->assertOk()->json('paiements');

        expect($paiements['parStatut']['reussi'])->toBe(['nombre' => 3, 'montant' => 12000])
            ->and($paiements['parStatut']['en_attente'])->toBe(['nombre' => 1, 'montant' => 6000])
            ->and($paiements['parStatut']['rembourse'])->toBe(['nombre' => 1, 'montant' => 3000])
            ->and($paiements['parStatut']['echoue'])->toBe(['nombre' => 1, 'montant' => 3000])
            ->and($paiements['total'])->toBe(['nombre' => 6, 'montant' => 24000]);
    });

    it('compte les litiges ouverts et clos', function () {
        Litige::factory()->count(2)->create();
        Litige::factory()->cloture()->count(3)->create();

        expect(tableauDeBord()->assertOk()->json('litiges'))->toBe(['total' => 5, 'ouverts' => 2, 'clos' => 3]);
    });

    it('compte les avis : total, signalés à modérer, masqués, moyenne des avis visibles', function () {
        $moderation = [
            'signale_le' => now(), 'motif_signalement' => 'Propos déplacés.',
            'modere_le' => now(), 'modere_par_id' => User::factory()->administrateur()->create()->id, 'motif_moderation' => 'Avis conforme.',
        ];
        Avis::factory()->note(5)->create();
        Avis::factory()->note(4)->create();
        Avis::factory()->note(2)->create(['signale_le' => now(), 'motif_signalement' => 'Propos déplacés.']);
        // Signalé et déjà modéré (conservé) : n'est plus « à modérer ».
        Avis::factory()->note(3)->create($moderation);
        // Masqué : exclu de la moyenne.
        Avis::factory()->note(1)->create($moderation + ['masque_le' => now()]);

        $avis = tableauDeBord()->assertOk()->json('avis');

        expect($avis['total'])->toBe(5)
            ->and($avis['signales'])->toBe(1)
            ->and($avis['masques'])->toBe(1)
            ->and($avis['noteMoyenne'])->toBe(3.5);
    });

    it('compte les sanctions actives : suspensions en cours et bannissements', function () {
        Sanction::factory()->suspension(now()->addDays(2))->create();
        // Suspension expirée, suspension levée, avertissement : non comptées.
        Sanction::factory()->suspension(now()->subDay())->create();
        Sanction::factory()->suspension(now()->addDays(2))->levee()->create();
        Sanction::factory()->create();
        Sanction::factory()->bannissement()->count(2)->create();
        Sanction::factory()->bannissement()->levee()->create();

        expect(tableauDeBord()->assertOk()->json('sanctions'))->toBe(['suspensionsEnCours' => 1, 'bannissements' => 2]);
    });
});

describe('tableau de bord — performance et confidentialité', function () {
    it('exécute un nombre de requêtes constant quel que soit le volume', function () {
        $petit = requetesTableauDeBord();

        Reservation::factory()->terminee()->count(6)->create();
        Paiement::factory()->reussi()->count(4)->create();
        Litige::factory()->count(3)->create();
        Avis::factory()->count(4)->create();
        Sanction::factory()->suspension()->count(3)->create();
        User::factory()->prestataire()->count(5)->create();

        $grand = requetesTableauDeBord();

        expect($grand)->toBe($petit);
    });

    it('n\'expose aucune donnée personnelle', function () {
        $client = User::factory()->create(['nom' => 'Diallo', 'prenom' => 'Awa', 'email' => 'awa.diallo@example.test']);
        Reservation::factory()->terminee()->create(['client_id' => $client->id]);
        Litige::factory()->create();

        $corps = tableauDeBord()->assertOk()->getContent();

        expect($corps)->not->toContain('Diallo')
            ->not->toContain('awa.diallo')
            ->not->toContain($client->telephone)
            ->not->toContain($client->id);
    });

    it('ne modifie rien en base', function () {
        Reservation::factory()->terminee()->create();
        Litige::factory()->create();
        $avant = [DB::table('reservations')->count(), DB::table('litiges')->count(), DB::table('notifications')->count(), DB::table('users')->count()];

        tableauDeBord()->assertOk();

        // +1 : l'administrateur créé par l'appel du test lui-même.
        expect([DB::table('reservations')->count(), DB::table('litiges')->count(), DB::table('notifications')->count(), DB::table('users')->count()])
            ->toBe([$avant[0], $avant[1], $avant[2], $avant[3] + 1]);
    });
});
