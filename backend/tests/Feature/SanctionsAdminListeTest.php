<?php

use App\Models\Avis;
use App\Models\Litige;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const URL_SANCTIONS_ADMIN = '/api/v1/admin/sanctions';

beforeEach(fn () => Carbon::setTestNow('2030-01-20 12:00:00'));

function idsSanctionsAdmin(string $requete = ''): array
{
    return collect(test()->getJson(URL_SANCTIONS_ADMIN.$requete)->assertOk()->json('data'))->pluck('id')->all();
}

describe('sanctions — liste admin', function () {
    it('exige une authentification', function () {
        $this->getJson(URL_SANCTIONS_ADMIN)->assertUnauthorized();
    });

    it('refuse un client et un prestataire (403)', function () {
        foreach ([User::factory()->create(), User::factory()->prestataire()->create()] as $intrus) {
            $this->actingAs($intrus, 'sanctum');
            $this->getJson(URL_SANCTIONS_ADMIN)->assertForbidden();
        }
    });

    it('renvoie une liste vide', function () {
        adminConnecte();

        $this->getJson(URL_SANCTIONS_ADMIN)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    });

    it('renvoie la forme exacte d\'une sanction levée et d\'une sanction liée à un litige', function () {
        adminConnecte();
        $cible = User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $litige = Litige::factory()->create();
        $suspension = Sanction::factory()->suspension(Carbon::parse('2030-02-01 09:00:00'))->create([
            'user_id' => $cible->id, 'litige_id' => $litige->id, 'motif' => 'Litige grave.', 'created_at' => '2030-01-15 10:00:00',
        ]);
        $levee = Sanction::factory()->levee()->create(['user_id' => $cible->id, 'created_at' => '2030-01-10 10:00:00']);

        $this->getJson(URL_SANCTIONS_ADMIN)->assertOk()->assertJsonPath('meta.total', 2)->assertJson(['data' => [
            [
                'id' => $suspension->id, 'type' => 'suspension_temporaire',
                'utilisateur' => ['id' => $cible->id, 'prenom' => 'Awa', 'nom' => 'Diallo', 'role' => 'client'],
                'motif' => 'Litige grave.', 'litigeId' => $litige->id, 'avisId' => null,
                'dateCreation' => '2030-01-15T10:00:00+00:00', 'dateFin' => '2030-02-01T09:00:00+00:00', 'active' => true, 'leveeLe' => null,
            ],
            [
                'id' => $levee->id, 'type' => 'avertissement', 'litigeId' => null, 'avisId' => null, 'dateFin' => null,
                'active' => false, 'leveeLe' => '2030-01-20T12:00:00+00:00',
            ],
        ]]);

        $ligne = $this->getJson(URL_SANCTIONS_ADMIN)->json('data.0');
        expect(array_keys($ligne))->toBe(['id', 'type', 'utilisateur', 'motif', 'litigeId', 'avisId', 'dateCreation', 'dateFin', 'active', 'leveeLe'])
            ->and(array_keys($ligne['utilisateur']))->toBe(['id', 'prenom', 'nom', 'role']);
    });

    it('expose l\'identifiant de l\'avis lié', function () {
        adminConnecte();
        $avis = Avis::factory()->create();
        $sanction = Sanction::factory()->create(['avis_id' => $avis->id]);

        $this->getJson(URL_SANCTIONS_ADMIN)->assertOk()->assertJsonPath('data.0.id', $sanction->id)->assertJsonPath('data.0.avisId', $avis->id);
    });

    it('trie du plus récent au plus ancien et pagine par 20', function () {
        adminConnecte();
        foreach (range(1, 25) as $i) {
            Sanction::factory()->create(['created_at' => Carbon::parse('2030-01-01 00:00:00')->addMinutes($i)]);
        }

        $page1 = $this->getJson(URL_SANCTIONS_ADMIN)->assertOk();
        expect($page1->json('data'))->toHaveCount(20)->and($page1->json('meta.total'))->toBe(25)
            ->and($page1->json('data.0.dateCreation'))->toBe('2030-01-01T00:25:00+00:00');
        expect($this->getJson(URL_SANCTIONS_ADMIN.'?page=2')->json('data'))->toHaveCount(5);
    });

    it('filtre par actives (booléens, textes true/false, 1/0)', function () {
        adminConnecte();
        $active = Sanction::factory()->create();
        $levee = Sanction::factory()->levee()->create();

        expect(idsSanctionsAdmin('?actives=true'))->toBe([$active->id])
            ->and(idsSanctionsAdmin('?actives=1'))->toBe([$active->id])
            ->and(idsSanctionsAdmin('?actives=false'))->toBe([$levee->id])
            ->and(idsSanctionsAdmin('?actives=0'))->toBe([$levee->id])
            ->and(idsSanctionsAdmin())->toHaveCount(2);
    });

    it('filtre par type', function () {
        adminConnecte();
        $a = Sanction::factory()->create();
        $s = Sanction::factory()->suspension()->create();
        $b = Sanction::factory()->bannissement()->create();

        expect(idsSanctionsAdmin('?type=avertissement'))->toBe([$a->id])
            ->and(idsSanctionsAdmin('?type=suspension_temporaire'))->toBe([$s->id])
            ->and(idsSanctionsAdmin('?type=bannissement_definitif'))->toBe([$b->id]);
    });

    it('filtre par utilisateur', function () {
        adminConnecte();
        $cible = User::factory()->create();
        $s = Sanction::factory()->create(['user_id' => $cible->id]);
        Sanction::factory()->create();

        expect(idsSanctionsAdmin('?utilisateur='.$cible->id))->toBe([$s->id])
            ->and(idsSanctionsAdmin('?utilisateur=0198a1b2-0000-7000-8000-00000000ffff'))->toBe([]);
    });

    it('filtre par échéance : suspensions non levées qui se terminent dans les N heures', function () {
        adminConnecte();
        $dans10h = Sanction::factory()->suspension(now()->addHours(10))->create();
        $dans48h = Sanction::factory()->suspension(now()->addHours(48))->create();
        Sanction::factory()->suspension(now()->subHour())->create(); // déjà expirée
        Sanction::factory()->suspension(now()->addHours(5))->levee()->create(); // levée
        Sanction::factory()->suspension(now()->addHours(500))->create(); // trop loin
        Sanction::factory()->create(); // autre type

        expect(idsSanctionsAdmin('?echeanceDansHeures=24'))->toBe([$dans10h->id])
            ->and(idsSanctionsAdmin('?echeance_dans_heures=48'))->toEqualCanonicalizing([$dans10h->id, $dans48h->id]);
    });

    it('combine les filtres', function () {
        adminConnecte();
        $cible = User::factory()->create();
        $voulue = Sanction::factory()->suspension(now()->addHours(3))->create(['user_id' => $cible->id]);
        Sanction::factory()->suspension(now()->addHours(3))->create();
        Sanction::factory()->create(['user_id' => $cible->id]);

        expect(idsSanctionsAdmin("?actives=true&type=suspension_temporaire&utilisateur={$cible->id}&echeanceDansHeures=24"))->toBe([$voulue->id]);
    });

    it('rejette des filtres invalides en français (422)', function (string $requete, string $champ, string $message) {
        adminConnecte();

        $this->getJson(URL_SANCTIONS_ADMIN.$requete)->assertUnprocessable()->assertJsonPath("errors.$champ.0", $message);
    })->with([
        'actives' => ['?actives=peut-etre', 'actives', 'Le filtre « actives » doit être vrai ou faux.'],
        'type' => ['?type=autre', 'type', 'Le type doit être avertissement, suspension_temporaire ou bannissement_definitif.'],
        'utilisateur' => ['?utilisateur=abc', 'utilisateur', 'L\'utilisateur doit être un identifiant valide.'],
        'échéance texte' => ['?echeanceDansHeures=abc', 'echeanceDansHeures', 'L\'échéance doit être un nombre entier d\'heures.'],
        'échéance trop grande' => ['?echeanceDansHeures=9000', 'echeanceDansHeures', 'L\'échéance doit être comprise entre 1 et 8760 heures.'],
        'échéance nulle' => ['?echeanceDansHeures=0', 'echeanceDansHeures', 'L\'échéance doit être comprise entre 1 et 8760 heures.'],
    ]);

    it('n\'expose aucune donnée sensible', function () {
        adminConnecte();
        $sanction = Sanction::factory()->create();

        $corps = strtolower($this->getJson(URL_SANCTIONS_ADMIN)->assertOk()->getContent());

        foreach (['password', 'token', 'jeton', 'email', 'telephone', 'remember', strtolower($sanction->user->password)] as $interdit) {
            expect($corps)->not->toContain($interdit);
        }
    });

    it('exécute un nombre de requêtes constant quel que soit le volume', function () {
        adminConnecte();
        Sanction::factory()->create();
        $petit = nombreDeRequetes(fn () => $this->getJson(URL_SANCTIONS_ADMIN)->assertOk());

        Sanction::factory()->count(10)->create();
        $grand = nombreDeRequetes(fn () => $this->getJson(URL_SANCTIONS_ADMIN)->assertOk());

        expect($grand)->toBe($petit);
    });
});
