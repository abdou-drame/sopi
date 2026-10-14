<?php

use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const URL_PAIEMENTS_ADMIN = '/api/v1/admin/paiements';

beforeEach(fn () => Carbon::setTestNow('2030-01-20 12:00:00'));

function idsPaiementsAdmin(string $requete = ''): array
{
    return collect(test()->getJson(URL_PAIEMENTS_ADMIN.$requete)->assertOk()->json('data'))->pluck('id')->all();
}

describe('paiements — accès', function () {
    it('exige une authentification', function () {
        $this->getJson(URL_PAIEMENTS_ADMIN)->assertUnauthorized();
        $this->getJson(URL_PAIEMENTS_ADMIN.'/'.Paiement::factory()->create()->id)->assertUnauthorized();
    });

    it('refuse un client et un prestataire (403)', function () {
        $cible = Paiement::factory()->create();
        foreach ([User::factory()->create(), User::factory()->prestataire()->create()] as $intrus) {
            $this->actingAs($intrus, 'sanctum');
            $this->getJson(URL_PAIEMENTS_ADMIN)->assertForbidden();
            $this->getJson(URL_PAIEMENTS_ADMIN.'/'.$cible->id)->assertForbidden();
        }
    });
});

describe('paiements — liste', function () {
    it('renvoie une liste vide', function () {
        adminConnecte();

        $this->getJson(URL_PAIEMENTS_ADMIN)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    });

    it('renvoie la forme exacte d\'une ligne, sans référence ni URL de paiement', function () {
        adminConnecte();
        $paiement = Paiement::factory()->reussi()->create([
            'montant_acompte' => '3000.00', 'commission' => '1000.00', 'paye_le' => '2030-01-20 09:00:00', 'reference_dexpay' => 'DXP-SECRET-REF',
            'url_paiement' => 'https://paiement.exemple/secret-url', 'remboursement_du_le' => '2030-01-20 10:00:00', 'remboursement_tentatives' => 3,
        ]);

        $reponse = $this->getJson(URL_PAIEMENTS_ADMIN)->assertOk();
        $reponse->assertJsonPath('data.0', [
            'id' => $paiement->id, 'reservationId' => $paiement->reservation_id, 'statut' => 'reussi', 'montantAcompte' => 3000, 'commission' => 1000,
            'remboursementDu' => true, 'remboursementDuLe' => '2030-01-20T10:00:00+00:00', 'tentativesRemboursement' => 3, 'tentativesEpuisees' => false,
            'payeLe' => '2030-01-20T09:00:00+00:00', 'rembourseLe' => null, 'crediteLe' => null,
        ]);

        $corps = strtolower($reponse->getContent());
        foreach (['dxp-secret-ref', 'secret-url', 'referencedexpay', 'urlpaiement', 'url_paiement', 'password', 'token', 'jeton', 'email', 'telephone'] as $interdit) {
            expect($corps)->not->toContain($interdit);
        }
    });

    it('trie du plus récent au plus ancien et pagine par 20', function () {
        adminConnecte();
        foreach (range(1, 25) as $i) {
            Paiement::factory()->create(['created_at' => Carbon::parse('2030-01-01 00:00:00')->addMinutes($i)]);
        }

        $page1 = $this->getJson(URL_PAIEMENTS_ADMIN)->assertOk();
        expect($page1->json('data'))->toHaveCount(20)->and($page1->json('meta.total'))->toBe(25);
        expect($this->getJson(URL_PAIEMENTS_ADMIN.'?page=2')->json('data'))->toHaveCount(5);
    });

    it('filtre par statut', function () {
        adminConnecte();
        $attente = Paiement::factory()->create();
        $reussi = Paiement::factory()->reussi()->create();
        $echoue = Paiement::factory()->echoue()->create();
        $rembourse = Paiement::factory()->rembourse()->create();

        expect(idsPaiementsAdmin('?statut=en_attente'))->toBe([$attente->id])
            ->and(idsPaiementsAdmin('?statut=reussi'))->toBe([$reussi->id])
            ->and(idsPaiementsAdmin('?statut=echoue'))->toBe([$echoue->id])
            ->and(idsPaiementsAdmin('?statut=rembourse'))->toBe([$rembourse->id]);
    });

    it('filtre par remboursement dû ou non (mêmes règles que les alertes)', function () {
        adminConnecte();
        $du = Paiement::factory()->reussi()->create(['remboursement_du_le' => now()->subHour()]);
        $duEchoue = Paiement::factory()->echoue()->create(['remboursement_du_le' => now()->subHour()]);
        $rembourse = Paiement::factory()->rembourse()->create(['remboursement_du_le' => now()->subHour()]); // déjà remboursé : plus dû
        $normal = Paiement::factory()->reussi()->create();
        $attente = Paiement::factory()->create();

        expect(idsPaiementsAdmin('?remboursementDu=true'))->toEqualCanonicalizing([$du->id, $duEchoue->id])
            ->and(idsPaiementsAdmin('?remboursement_du=1'))->toEqualCanonicalizing([$du->id, $duEchoue->id])
            ->and(idsPaiementsAdmin('?remboursementDu=false'))->toEqualCanonicalizing([$rembourse->id, $normal->id, $attente->id]);
    });

    it('filtre par retard de remboursement, essais épuisés compris', function () {
        adminConnecte();
        $retard = Paiement::factory()->reussi()->create(['remboursement_du_le' => now()->subMinutes(45), 'remboursement_tentatives' => 2]);
        $epuise = Paiement::factory()->reussi()->create(['remboursement_du_le' => now()->subMinutes(500), 'remboursement_tentatives' => 10]);
        Paiement::factory()->reussi()->create(['remboursement_du_le' => now()->subMinutes(10)]); // trop récent
        Paiement::factory()->reussi()->create(); // pas dû
        Paiement::factory()->rembourse()->create(['remboursement_du_le' => now()->subMinutes(500)]); // déjà remboursé

        expect(idsPaiementsAdmin('?retardRemboursementMinutes=30'))->toEqualCanonicalizing([$retard->id, $epuise->id])
            ->and(idsPaiementsAdmin('?retard_remboursement_minutes=60'))->toBe([$epuise->id])
            ->and(idsPaiementsAdmin('?retardRemboursementMinutes=30&remboursementDu=true'))->toEqualCanonicalizing([$retard->id, $epuise->id]);

        $lignes = collect($this->getJson(URL_PAIEMENTS_ADMIN.'?retardRemboursementMinutes=30')->json('data'))->keyBy('id');
        expect($lignes[$epuise->id]['tentativesEpuisees'])->toBeTrue()->and($lignes[$retard->id]['tentativesEpuisees'])->toBeFalse();
    });

    it('filtre par réservation', function () {
        adminConnecte();
        $reservation = Reservation::factory()->acceptee()->create();
        $voulu = Paiement::factory()->for($reservation)->echoue()->create();
        Paiement::factory()->create();

        expect(idsPaiementsAdmin('?reservation='.$reservation->id))->toBe([$voulu->id])
            ->and(idsPaiementsAdmin('?reservation=0198a1b2-0000-7000-8000-00000000ffff'))->toBe([]);
    });

    it('combine les filtres', function () {
        adminConnecte();
        $reservation = Reservation::factory()->annulee()->create();
        $voulu = Paiement::factory()->for($reservation)->reussi()->create(['remboursement_du_le' => now()->subHours(2)]);
        Paiement::factory()->reussi()->create(['remboursement_du_le' => now()->subHours(2)]);
        Paiement::factory()->for(Reservation::factory()->annulee()->create())->echoue()->create(['remboursement_du_le' => now()->subHours(2)]);

        expect(idsPaiementsAdmin("?statut=reussi&remboursementDu=true&retardRemboursementMinutes=60&reservation={$reservation->id}"))->toBe([$voulu->id]);
    });

    it('rejette des filtres invalides en français (422)', function (string $requete, string $champ, string $message) {
        adminConnecte();

        $this->getJson(URL_PAIEMENTS_ADMIN.$requete)->assertUnprocessable()->assertJsonPath("errors.$champ.0", $message);
    })->with([
        'statut' => ['?statut=inconnu', 'statut', 'Le statut doit être en_attente, reussi, echoue ou rembourse.'],
        'remboursementDu' => ['?remboursementDu=peut-etre', 'remboursementDu', 'Le filtre « remboursementDu » doit être vrai ou faux.'],
        'retard texte' => ['?retardRemboursementMinutes=abc', 'retardRemboursementMinutes', 'Le retard doit être un nombre entier de minutes.'],
        'retard nul' => ['?retardRemboursementMinutes=0', 'retardRemboursementMinutes', 'Le retard doit être compris entre 1 et 525600 minutes.'],
        'réservation' => ['?reservation=abc', 'reservation', 'La réservation doit être un identifiant valide.'],
        'page' => ['?page=0', 'page', 'La page doit être au moins 1.'],
    ]);

    it('exécute un nombre de requêtes constant quel que soit le volume', function () {
        adminConnecte();
        Paiement::factory()->reussi()->create();
        $petit = nombreDeRequetes(fn () => $this->getJson(URL_PAIEMENTS_ADMIN)->assertOk());

        Paiement::factory()->reussi()->count(10)->create();
        $grand = nombreDeRequetes(fn () => $this->getJson(URL_PAIEMENTS_ADMIN)->assertOk());

        expect($grand)->toBe($petit);
    });
});

describe('paiements — détail', function () {
    it('répond 404 pour un identifiant inconnu ou mal formé', function () {
        adminConnecte();

        foreach (['0198a1b2-0000-7000-8000-00000000ffff', 'pas-un-uuid'] as $id) {
            $this->getJson(URL_PAIEMENTS_ADMIN.'/'.$id)->assertNotFound()->assertExactJson(['message' => 'Paiement introuvable.']);
        }
    });

    it('renvoie le détail exact avec la référence DexPay mais sans URL de paiement', function () {
        adminConnecte();
        $reservation = Reservation::factory()->annulee()->create(['prix_applique' => '10000.00']);
        $paiement = Paiement::factory()->for($reservation)->reussi()->create([
            'montant_acompte' => '3000.00', 'commission' => '1000.00', 'paye_le' => '2030-01-20 09:00:00', 'reference_dexpay' => 'DXP-REF-VISIBLE',
            'url_paiement' => 'https://paiement.exemple/secret-url', 'remboursement_du_le' => '2030-01-20 10:00:00', 'remboursement_tentatives' => 10,
            'credite_le' => '2030-01-20 11:00:00', 'created_at' => '2030-01-20 08:45:00',
        ]);

        $reponse = $this->getJson(URL_PAIEMENTS_ADMIN.'/'.$paiement->id)->assertOk();
        $reponse->assertExactJson([
            'id' => $paiement->id, 'reservationId' => $reservation->id, 'statut' => 'reussi', 'montantAcompte' => 3000, 'commission' => 1000,
            'remboursementDu' => true, 'remboursementDuLe' => '2030-01-20T10:00:00+00:00', 'tentativesRemboursement' => 10, 'tentativesEpuisees' => true,
            'payeLe' => '2030-01-20T09:00:00+00:00', 'rembourseLe' => null, 'crediteLe' => '2030-01-20T11:00:00+00:00',
            'reservation' => [
                'id' => $reservation->id, 'statut' => 'annulee', 'prixApplique' => 10000, 'clientId' => $reservation->client_id,
                'prestataireId' => $reservation->creneau->prestataire_id,
            ],
            'creeLe' => '2030-01-20T08:45:00+00:00', 'tentativesMaximum' => 10, 'referenceDexpay' => 'DXP-REF-VISIBLE',
        ]);
        expect(strtolower($reponse->getContent()))->not->toContain('secret-url');
    });

    it('renvoie une référence nulle tant que la session n\'existe pas', function () {
        adminConnecte();
        $paiement = Paiement::factory()->create();

        $this->getJson(URL_PAIEMENTS_ADMIN.'/'.$paiement->id)->assertOk()->assertJsonPath('referenceDexpay', null)->assertJsonPath('statut', 'en_attente');
    });

    it('exécute un nombre de requêtes constant', function () {
        adminConnecte();
        $a = Paiement::factory()->reussi()->create();
        $b = Paiement::factory()->reussi()->create(['remboursement_du_le' => now(), 'remboursement_tentatives' => 5]);

        expect(nombreDeRequetes(fn () => $this->getJson(URL_PAIEMENTS_ADMIN.'/'.$b->id)->assertOk()))
            ->toBe(nombreDeRequetes(fn () => $this->getJson(URL_PAIEMENTS_ADMIN.'/'.$a->id)->assertOk()));
    });
});
