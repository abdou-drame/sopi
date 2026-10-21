<?php

use App\Models\Prestataire;
use App\Models\Retrait;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const URL_RETRAITS_ADMIN = '/api/v1/admin/retraits';

beforeEach(function () {
    Carbon::setTestNow('2030-01-20 12:00:00');
    config(['sopi.retraits.delai_blocage_minutes' => 15]);
});

function idsRetraitsAdmin(string $requete = ''): array
{
    return collect(test()->getJson(URL_RETRAITS_ADMIN.$requete)->assertOk()->json('data'))->pluck('id')->all();
}

describe('retraits — accès', function () {
    it('exige une authentification', function () {
        $this->getJson(URL_RETRAITS_ADMIN)->assertUnauthorized();
        $this->getJson(URL_RETRAITS_ADMIN.'/'.Retrait::factory()->create()->id)->assertUnauthorized();
    });

    it('refuse un client et un prestataire (403)', function () {
        $cible = Retrait::factory()->create();
        foreach ([User::factory()->create(), User::factory()->prestataire()->create()] as $intrus) {
            $this->actingAs($intrus, 'sanctum');
            $this->getJson(URL_RETRAITS_ADMIN)->assertForbidden();
            $this->getJson(URL_RETRAITS_ADMIN.'/'.$cible->id)->assertForbidden();
        }
    });
});

describe('retraits — liste', function () {
    it('renvoie une liste vide', function () {
        adminConnecte();

        $this->getJson(URL_RETRAITS_ADMIN)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    });

    it('renvoie la forme exacte d\'une ligne, téléphone masqué et sans référence', function () {
        adminConnecte();
        $prestataire = Prestataire::factory()->create(['nom_entreprise' => 'Garage Exemple']);
        $retrait = Retrait::factory()->reussi()->create([
            'prestataire_id' => $prestataire->id, 'montant' => '5000.00', 'telephone_versement' => '+221771234567', 'reference_dexpay' => 'DXP-RET-SECRET',
            'traite_le' => '2030-01-20 08:05:00', 'created_at' => '2030-01-20 08:00:00',
        ]);

        $reponse = $this->getJson(URL_RETRAITS_ADMIN)->assertOk();
        $reponse->assertJsonPath('data.0', [
            'id' => $retrait->id,
            'prestataire' => ['id' => $prestataire->id, 'userId' => $prestataire->user_id, 'nomEntreprise' => 'Garage Exemple'],
            'montant' => 5000, 'statut' => 'reussi', 'operateur' => 'wave', 'bloque' => false, 'dateDemande' => '2030-01-20T08:00:00+00:00',
            'traiteLe' => '2030-01-20T08:05:00+00:00', 'recredite' => false, 'telephoneVersement' => '+221*******67',
        ]);

        $corps = strtolower($reponse->getContent());
        foreach (['771234567', 'dxp-ret-secret', 'referencedexpay', 'password', 'token', 'jeton', 'email'] as $interdit) {
            expect($corps)->not->toContain($interdit);
        }
    });

    it('trie du plus récent au plus ancien et pagine par 20', function () {
        adminConnecte();
        foreach (range(1, 25) as $i) {
            Retrait::factory()->create(['created_at' => Carbon::parse('2030-01-01 00:00:00')->addMinutes($i)]);
        }

        $page1 = $this->getJson(URL_RETRAITS_ADMIN)->assertOk();
        expect($page1->json('data'))->toHaveCount(20)->and($page1->json('meta.total'))->toBe(25)
            ->and($page1->json('data.0.dateDemande'))->toBe('2030-01-01T00:25:00+00:00');
        expect($this->getJson(URL_RETRAITS_ADMIN.'?page=2')->json('data'))->toHaveCount(5);
    });

    it('filtre par statut', function () {
        adminConnecte();
        $encours = Retrait::factory()->create();
        $reussi = Retrait::factory()->reussi()->create();
        $echoue = Retrait::factory()->echoue()->create();

        expect(idsRetraitsAdmin('?statut=en_cours'))->toBe([$encours->id])
            ->and(idsRetraitsAdmin('?statut=reussi'))->toBe([$reussi->id])
            ->and(idsRetraitsAdmin('?statut=echoue'))->toBe([$echoue->id]);
    });

    it('filtre par prestataire', function () {
        adminConnecte();
        $prestataire = Prestataire::factory()->create();
        $voulu = Retrait::factory()->create(['prestataire_id' => $prestataire->id]);
        Retrait::factory()->create();

        expect(idsRetraitsAdmin('?prestataire='.$prestataire->id))->toBe([$voulu->id])
            ->and(idsRetraitsAdmin('?prestataire=0198a1b2-0000-7000-8000-00000000ffff'))->toBe([]);
    });

    it('filtre les retraits bloqués (en cours depuis plus que le délai de blocage)', function () {
        adminConnecte();
        $bloque = Retrait::factory()->create(['created_at' => now()->subMinutes(30)]);
        $limite = Retrait::factory()->create(['created_at' => now()->subMinutes(15)]); // pile au délai : bloqué, comme l'alerte
        $recent = Retrait::factory()->create(['created_at' => now()->subMinutes(5)]);
        $ancienReussi = Retrait::factory()->reussi()->create(['created_at' => now()->subHours(5)]);

        expect(idsRetraitsAdmin('?bloques=true'))->toEqualCanonicalizing([$bloque->id, $limite->id])
            ->and(idsRetraitsAdmin('?bloques=false'))->toEqualCanonicalizing([$recent->id, $ancienReussi->id]);

        $lignes = collect($this->getJson(URL_RETRAITS_ADMIN)->json('data'))->keyBy('id');
        expect($lignes[$bloque->id]['bloque'])->toBeTrue()->and($lignes[$recent->id]['bloque'])->toBeFalse()->and($lignes[$ancienReussi->id]['bloque'])->toBeFalse();
    });

    it('combine les filtres', function () {
        adminConnecte();
        $prestataire = Prestataire::factory()->create();
        $voulu = Retrait::factory()->create(['prestataire_id' => $prestataire->id, 'created_at' => now()->subHour()]);
        Retrait::factory()->reussi()->create(['prestataire_id' => $prestataire->id, 'created_at' => now()->subHour()]);
        Retrait::factory()->create(['created_at' => now()->subHour()]);

        expect(idsRetraitsAdmin("?statut=en_cours&bloques=true&prestataire={$prestataire->id}"))->toBe([$voulu->id]);
    });

    it('rejette des filtres invalides en français (422)', function (string $requete, string $champ, string $message) {
        adminConnecte();

        $this->getJson(URL_RETRAITS_ADMIN.$requete)->assertUnprocessable()->assertJsonPath("errors.$champ.0", $message);
    })->with([
        'statut' => ['?statut=inconnu', 'statut', 'Le statut doit être en_cours, reussi ou echoue.'],
        'prestataire' => ['?prestataire=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'prestataire', 'Le nom du prestataire ne peut pas dépasser 100 caractères.'],
        'bloques' => ['?bloques=peut-etre', 'bloques', 'Le filtre « bloques » doit être vrai ou faux.'],
        'page' => ['?page=0', 'page', 'La page doit être au moins 1.'],
    ]);

    it('exécute un nombre de requêtes constant quel que soit le volume', function () {
        adminConnecte();
        Retrait::factory()->create();
        $petit = nombreDeRequetes(fn () => $this->getJson(URL_RETRAITS_ADMIN)->assertOk());

        Retrait::factory()->count(10)->create();
        $grand = nombreDeRequetes(fn () => $this->getJson(URL_RETRAITS_ADMIN)->assertOk());

        expect($grand)->toBe($petit);
    });
});

describe('retraits — détail', function () {
    it('répond 404 pour un identifiant inconnu ou mal formé', function () {
        adminConnecte();

        foreach (['0198a1b2-0000-7000-8000-00000000ffff', 'pas-un-uuid'] as $id) {
            $this->getJson(URL_RETRAITS_ADMIN.'/'.$id)->assertNotFound()->assertExactJson(['message' => 'Retrait introuvable.']);
        }
    });

    it('renvoie le détail exact avec téléphone complet et référence DexPay', function () {
        adminConnecte();
        $prestataire = Prestataire::factory()->create(['nom_entreprise' => 'Garage Exemple']);
        $retrait = Retrait::factory()->echoue()->create([
            'prestataire_id' => $prestataire->id, 'montant' => '5000.00', 'telephone_versement' => '+221771234567', 'reference_dexpay' => 'DXP-RET-VISIBLE',
            'traite_le' => '2030-01-20 08:05:00', 'recredite_le' => '2030-01-20 08:06:00', 'created_at' => '2030-01-20 08:00:00',
        ]);

        $this->getJson(URL_RETRAITS_ADMIN.'/'.$retrait->id)->assertOk()->assertExactJson([
            'id' => $retrait->id,
            'prestataire' => ['id' => $prestataire->id, 'userId' => $prestataire->user_id, 'nomEntreprise' => 'Garage Exemple'],
            'montant' => 5000, 'statut' => 'echoue', 'operateur' => 'wave', 'bloque' => false, 'dateDemande' => '2030-01-20T08:00:00+00:00',
            'traiteLe' => '2030-01-20T08:05:00+00:00', 'recredite' => true,
            'telephoneVersement' => '+221771234567', 'referenceDexpay' => 'DXP-RET-VISIBLE', 'recrediteLe' => '2030-01-20T08:06:00+00:00',
        ]);
    });

    it('signale un retrait bloqué dans le détail', function () {
        adminConnecte();
        $retrait = Retrait::factory()->create(['created_at' => now()->subHour()]);

        $this->getJson(URL_RETRAITS_ADMIN.'/'.$retrait->id)->assertOk()->assertJsonPath('bloque', true)->assertJsonPath('referenceDexpay', null);
    });

    it('exécute un nombre de requêtes constant', function () {
        adminConnecte();
        $a = Retrait::factory()->create();
        $b = Retrait::factory()->reussi()->create();

        expect(nombreDeRequetes(fn () => $this->getJson(URL_RETRAITS_ADMIN.'/'.$b->id)->assertOk()))
            ->toBe(nombreDeRequetes(fn () => $this->getJson(URL_RETRAITS_ADMIN.'/'.$a->id)->assertOk()));
    });
});
