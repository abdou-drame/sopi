<?php

use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Retrait;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const URL_REVENUS = '/api/v1/admin/revenus';

function revenus(string $requete = ''): TestResponse
{
    return test()->actingAs(User::factory()->administrateur()->create(), 'sanctum')->getJson(URL_REVENUS.$requete);
}

function paiementCredite(string $commission, string $creditLe, string $acompte = '20000.00'): Paiement
{
    return Paiement::factory()->reussi()->create([
        'montant_acompte' => $acompte,
        'commission' => $commission,
        'paye_le' => Carbon::parse($creditLe)->subDay(),
        'credite_le' => $creditLe,
    ]);
}

describe('revenus — accès et validation', function () {
    it('exige une authentification', function () {
        $this->getJson(URL_REVENUS)->assertUnauthorized();
    });

    it('refuse un client et un prestataire (403)', function () {
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson(URL_REVENUS)->assertForbidden();
        $this->actingAs(User::factory()->prestataire()->create(), 'sanctum')->getJson(URL_REVENUS)->assertForbidden();
    });

    it('rejette les paramètres invalides en français (422)', function (string $requete, string $champ, string $message) {
        $reponse = revenus($requete)->assertStatus(422);

        expect($reponse->json("errors.$champ.0"))->toBe($message)
            ->and($reponse->json('message'))->toBeString();
    })->with([
        'date de début invalide' => ['?debut=demain', 'debut', 'La date de début doit être au format AAAA-MM-JJ.'],
        'jour inexistant' => ['?debut=2030-02-31', 'debut', 'La date de début doit être au format AAAA-MM-JJ.'],
        'date de fin invalide' => ['?fin=2030-13-01', 'fin', 'La date de fin doit être au format AAAA-MM-JJ.'],
        'fin avant début' => ['?debut=2030-01-10&fin=2030-01-09', 'fin', 'La date de fin doit être postérieure ou égale à la date de début.'],
        'plus de 366 jours' => ['?debut=2030-01-01&fin=2031-01-02', 'fin', 'La période ne peut pas dépasser 366 jours.'],
        'regroupement inconnu' => ['?regroupement=annee', 'regroupement', 'Le regroupement doit être « jour », « semaine » ou « mois ».'],
    ]);

    it('accepte exactement 366 jours et un seul jour', function () {
        revenus('?debut=2030-01-01&fin=2031-01-01')->assertOk();
        revenus('?debut=2030-01-01&fin=2030-01-01')->assertOk()->assertJsonCount(1, 'series');
    });
});

describe('revenus — valeurs par défaut', function () {
    it('prend les 30 derniers jours, par jour', function () {
        Carbon::setTestNow('2030-01-30 15:00:00');

        $reponse = revenus()->assertOk();

        expect($reponse->json('periode'))->toBe(['debut' => '2030-01-01', 'fin' => '2030-01-30', 'regroupement' => 'jour', 'fuseau' => 'Africa/Dakar'])
            ->and($reponse->json('series'))->toHaveCount(30)
            ->and($reponse->json('series.0'))->toBe(['debut' => '2030-01-01', 'fin' => '2030-01-01', 'revenus' => 0, 'acomptesEncaisses' => 0, 'remboursements' => 0, 'retraitsVerses' => 0])
            ->and($reponse->json('totaux'))->toBe(['revenus' => 0, 'acomptesEncaisses' => 0, 'remboursements' => 0, 'retraitsVerses' => 0])
            ->and($reponse->json('soldePrestataires'))->toBe(0);
    });

    it('si seul le début est donné, la fin est aujourd\'hui', function () {
        Carbon::setTestNow('2030-01-10 08:00:00');

        expect(revenus('?debut=2030-01-08')->assertOk()->json('series'))->toHaveCount(3);
    });
});

describe('revenus — calculs sur un jeu de données connu', function () {
    beforeEach(fn () => Carbon::setTestNow('2030-02-15 12:00:00'));

    it('ne compte que les paiements réussis et crédités, datés par le crédit', function () {
        paiementCredite('1000.00', '2030-01-10 09:00:00');
        paiementCredite('1500.00', '2030-01-10 18:30:00');
        paiementCredite('2000.00', '2030-01-12 00:00:00');
        // Non crédité (prestation pas terminée) : exclu.
        Paiement::factory()->reussi()->create(['montant_acompte' => '30000.00', 'commission' => '9000.00', 'paye_le' => '2030-01-10 10:00:00']);
        // Remboursé, même crédité : exclu.
        Paiement::factory()->rembourse()->create(['montant_acompte' => '30000.00', 'commission' => '8000.00', 'credite_le' => '2030-01-10 10:00:00']);
        // Hors période.
        paiementCredite('7000.00', '2030-01-13 00:00:00');
        paiementCredite('7000.00', '2030-01-09 23:59:59');

        $reponse = revenus('?debut=2030-01-10&fin=2030-01-12')->assertOk();

        expect($reponse->json('totaux.revenus'))->toBe(4500)
            ->and(array_column($reponse->json('series'), 'revenus'))->toBe([2500, 0, 2000]);
    });

    it('inclut les deux bornes de la période (00:00:00 du premier jour, 23:59:59 du dernier)', function () {
        paiementCredite('1000.00', '2030-01-10 00:00:00');
        paiementCredite('2000.00', '2030-01-12 23:59:59');

        $reponse = revenus('?debut=2030-01-10&fin=2030-01-12')->assertOk();

        expect(array_column($reponse->json('series'), 'revenus'))->toBe([1000, 0, 2000]);
    });

    it('totalise acomptes encaissés, remboursements et retraits versés, chacun daté par son propre événement', function () {
        Paiement::factory()->reussi()->create(['montant_acompte' => '4500.00', 'commission' => '1500.00', 'paye_le' => '2030-01-10 10:00:00']);
        Paiement::factory()->reussi()->create(['montant_acompte' => '3000.00', 'paye_le' => '2030-01-11 10:00:00']);
        Paiement::factory()->enAttente()->create(['montant_acompte' => '6000.00']);
        Paiement::factory()->echoue()->create(['montant_acompte' => '6000.00']);
        Paiement::factory()->rembourse()->create(['montant_acompte' => '3000.00', 'paye_le' => '2030-01-05 10:00:00', 'rembourse_le' => '2030-01-11 12:00:00']);
        Retrait::factory()->reussi()->create(['montant' => '10000.00', 'traite_le' => '2030-01-11 08:00:00']);
        Retrait::factory()->reussi()->create(['montant' => '5000.00', 'traite_le' => '2030-02-01 08:00:00']);
        Retrait::factory()->enCours()->create(['montant' => '20000.00']);
        Retrait::factory()->echoue()->create(['montant' => '30000.00', 'traite_le' => '2030-01-11 08:00:00']);

        $reponse = revenus('?debut=2030-01-10&fin=2030-01-11')->assertOk();

        expect($reponse->json('totaux'))->toBe(['revenus' => 0, 'acomptesEncaisses' => 7500, 'remboursements' => 3000, 'retraitsVerses' => 10000])
            ->and($reponse->json('series.0'))->toBe(['debut' => '2030-01-10', 'fin' => '2030-01-10', 'revenus' => 0, 'acomptesEncaisses' => 4500, 'remboursements' => 0, 'retraitsVerses' => 0])
            ->and($reponse->json('series.1'))->toBe(['debut' => '2030-01-11', 'fin' => '2030-01-11', 'revenus' => 0, 'acomptesEncaisses' => 3000, 'remboursements' => 3000, 'retraitsVerses' => 10000]);
    });

    it('renvoie le cumul des soldes des prestataires, indépendant de la période', function () {
        Prestataire::factory()->avoirSolde('12000.00')->create();
        Prestataire::factory()->avoirSolde('8000.00')->create();
        Prestataire::factory()->create();

        expect(revenus('?debut=2020-01-01&fin=2020-01-02')->assertOk()->json('soldePrestataires'))->toBe(20000);
    });

    it('groupe par semaine (lundi à dimanche), les extrémités étant tronquées à la période', function () {
        // 2030-01-07 est un lundi.
        paiementCredite('1000.00', '2030-01-03 10:00:00'); // jeudi, semaine du 31/12
        paiementCredite('2000.00', '2030-01-06 10:00:00'); // dimanche, même semaine
        paiementCredite('4000.00', '2030-01-07 00:00:00'); // lundi, semaine suivante
        paiementCredite('8000.00', '2030-01-16 10:00:00'); // mercredi, 3e semaine, tronquée

        $reponse = revenus('?debut=2030-01-02&fin=2030-01-16&regroupement=semaine')->assertOk();

        expect(array_map(fn ($s) => [$s['debut'], $s['fin'], $s['revenus']], $reponse->json('series')))->toBe([
            ['2030-01-02', '2030-01-06', 3000],
            ['2030-01-07', '2030-01-13', 4000],
            ['2030-01-14', '2030-01-16', 8000],
        ])->and($reponse->json('totaux.revenus'))->toBe(15000);
    });

    it('groupe par mois calendaire, périodes vides comprises', function () {
        paiementCredite('1000.00', '2030-01-31 23:59:59');
        paiementCredite('2000.00', '2030-03-01 00:00:00');
        paiementCredite('500.00', '2030-03-20 10:00:00');

        $reponse = revenus('?debut=2030-01-15&fin=2030-04-10&regroupement=mois')->assertOk();

        expect(array_map(fn ($s) => [$s['debut'], $s['fin'], $s['revenus']], $reponse->json('series')))->toBe([
            ['2030-01-15', '2030-01-31', 1000],
            ['2030-02-01', '2030-02-28', 0],
            ['2030-03-01', '2030-03-31', 2500],
            ['2030-04-01', '2030-04-10', 0],
        ]);
    });

    it('somme les montants en FCFA entiers, sans flottant', function () {
        paiementCredite('1000.00', '2030-01-10 10:00:00');
        paiementCredite('1500.00', '2030-01-10 11:00:00');
        paiementCredite('333.00', '2030-01-10 12:00:00');

        $reponse = revenus('?debut=2030-01-10&fin=2030-01-10')->assertOk();

        expect($reponse->json('totaux.revenus'))->toBeInt()->toBe(2833)
            ->and($reponse->json('series.0.revenus'))->toBeInt();
    });

    it('interprète les jours dans le fuseau de l\'application (Africa/Dakar)', function () {
        expect(config('app.timezone'))->toBe('Africa/Dakar');
        // Écrit via Eloquent (fuseau de l'application) : le jour retenu est celui de l'heure locale.
        $paiement = paiementCredite('1000.00', '2030-01-10 23:30:00');

        expect(revenus('?debut=2030-01-10&fin=2030-01-10')->assertOk()->json('totaux.revenus'))->toBe(1000)
            ->and(revenus('?debut=2030-01-11&fin=2030-01-11')->assertOk()->json('totaux.revenus'))->toBe(0)
            ->and($paiement->fresh()->credite_le->timezoneName)->toBe('Africa/Dakar');
    });
});

describe('revenus — performance et confidentialité', function () {
    it('exécute un nombre de requêtes constant quel que soit le volume', function () {
        Carbon::setTestNow('2030-02-15 12:00:00');
        $compter = function () {
            $admin = User::factory()->administrateur()->create();
            DB::flushQueryLog();
            DB::enableQueryLog();
            test()->actingAs($admin, 'sanctum')->getJson(URL_REVENUS.'?debut=2030-01-01&fin=2030-01-31&regroupement=semaine')->assertOk();
            $nombre = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $nombre;
        };

        $petit = $compter();
        foreach (range(1, 6) as $jour) {
            paiementCredite('1000.00', "2030-01-0$jour 10:00:00");
        }
        Retrait::factory()->reussi()->count(4)->create(['traite_le' => '2030-01-05 10:00:00']);
        Prestataire::factory()->count(3)->create();

        expect($compter())->toBe($petit);
    });

    it('n\'expose aucune donnée personnelle ni aucun montant nominatif', function () {
        $retrait = Retrait::factory()->reussi()->create(['traite_le' => now()]);
        $paiement = paiementCredite('1000.00', now()->toDateTimeString());

        $corps = revenus()->assertOk()->getContent();

        expect($corps)->not->toContain($retrait->telephone_versement)
            ->not->toContain($retrait->id)
            ->not->toContain($paiement->id)
            ->not->toContain((string) $paiement->reference_dexpay);
    });
});

describe('revenus — par métier', function () {
    it('ventile les commissions de la période par métier du prestataire, du plus élevé au plus bas', function () {
        $p1 = paiementCredite('1000.00', '2030-01-10 10:00:00');
        $p2 = paiementCredite('2500.00', '2030-01-11 10:00:00');
        $p3 = paiementCredite('700.00', '2030-03-01 10:00:00'); // hors période
        DB::table('prestataires')->whereIn('id', [$p1->reservation->creneau->prestataire_id])->update(['metier' => 'coiffure']);
        DB::table('prestataires')->whereIn('id', [$p2->reservation->creneau->prestataire_id])->update(['metier' => 'pressing']);

        $reponse = revenus('?debut=2030-01-01&fin=2030-01-31')->assertOk();

        expect($reponse->json('parMetier'))->toBe([
            ['metier' => 'pressing', 'revenus' => 2500],
            ['metier' => 'coiffure', 'revenus' => 1000],
        ]);
    });
});
