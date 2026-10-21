<?php

use App\Models\Avis;
use App\Models\Creneau;
use App\Models\Litige;
use App\Models\Paiement;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const URL_RESERVATIONS_ADMIN = '/api/v1/admin/reservations';

beforeEach(fn () => Carbon::setTestNow('2030-01-20 12:00:00'));

function idsReservationsAdmin(string $requete = ''): array
{
    return collect(test()->getJson(URL_RESERVATIONS_ADMIN.$requete)->assertOk()->json('data'))->pluck('id')->all();
}

/** Réservation d'un créneau du prestataire donné (créneau neuf, à la date donnée). */
function reservationPourAdmin(Prestataire $prestataire, string $etat = 'enAttente', array $attributs = [], ?string $debutCreneau = null): Reservation
{
    $creneau = Creneau::factory()->for($prestataire)->create($debutCreneau === null ? [] : ['date_debut' => $debutCreneau, 'date_fin' => Carbon::parse($debutCreneau)->addHour()]);

    return Reservation::factory()->{$etat}()->create(['creneau_id' => $creneau->id] + $attributs);
}

describe('réservations — accès', function () {
    it('exige une authentification', function () {
        $this->getJson(URL_RESERVATIONS_ADMIN)->assertUnauthorized();
        $this->getJson(URL_RESERVATIONS_ADMIN.'/'.Reservation::factory()->create()->id)->assertUnauthorized();
    });

    it('refuse un client et un prestataire (403)', function () {
        $cible = Reservation::factory()->create();
        foreach ([User::factory()->create(), User::factory()->prestataire()->create()] as $intrus) {
            $this->actingAs($intrus, 'sanctum');
            $this->getJson(URL_RESERVATIONS_ADMIN)->assertForbidden();
            $this->getJson(URL_RESERVATIONS_ADMIN.'/'.$cible->id)->assertForbidden();
        }
    });
});

describe('réservations — liste', function () {
    it('renvoie une liste vide', function () {
        adminConnecte();

        $this->getJson(URL_RESERVATIONS_ADMIN)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    });

    it('renvoie la forme exacte d\'une ligne', function () {
        adminConnecte();
        $client = User::factory()->create(['prenom' => 'Awa', 'nom' => 'diallo']);
        $prestataire = Prestataire::factory()->create(['nom_entreprise' => 'Garage Exemple']);
        $prestation = Prestation::factory()->for($prestataire)->create(['nom' => 'Vidange', 'prix' => 10000]);
        $creneau = Creneau::factory()->for($prestataire)->create(['date_debut' => '2030-01-21 09:00:00', 'date_fin' => '2030-01-21 10:00:00']);
        $reservation = Reservation::factory()->acceptee()->create([
            'client_id' => $client->id, 'creneau_id' => $creneau->id, 'prestation_id' => $prestation->id, 'prix_applique' => '10000.00', 'created_at' => '2030-01-20 08:00:00',
        ]);
        $paiement = Paiement::factory()->for($reservation)->reussi()->create();

        $reponse = $this->getJson(URL_RESERVATIONS_ADMIN)->assertOk();
        $reponse->assertJson(['data' => [[
            'id' => $reservation->id, 'statut' => $reservation->statut->value,
            'client' => ['id' => $client->id, 'prenom' => 'Awa', 'initialeNom' => 'D.'],
            'prestataire' => ['id' => $prestataire->id, 'nomEntreprise' => 'Garage Exemple'],
            'prestation' => ['id' => $prestation->id, 'nom' => 'Vidange'],
            'creneau' => ['id' => $creneau->id, 'debut' => '2030-01-21T09:00:00+00:00', 'fin' => '2030-01-21T10:00:00+00:00'],
            'prixApplique' => 10000, 'statutPaiement' => 'reussi', 'dateCreation' => '2030-01-20T08:00:00+00:00',
        ]]]);
        expect(array_keys($reponse->json('data.0')))->toBe(['id', 'statut', 'client', 'prestataire', 'prestation', 'creneau', 'prixApplique', 'statutPaiement', 'dateCreation'])
            ->and(array_keys($reponse->json('data.0.client')))->toBe(['id', 'prenom', 'initialeNom']);
        expect($paiement->statut->value)->toBe('reussi');
    });

    it('donne le statut du paiement qui représente la réservation, ou null', function () {
        adminConnecte();
        $sans = Reservation::factory()->create();
        $reussi = Reservation::factory()->confirmee()->create();
        Paiement::factory()->for($reussi)->echoue()->create(['created_at' => '2030-01-19 10:00:00']);
        Paiement::factory()->for($reussi)->reussi()->create(['created_at' => '2030-01-19 11:00:00']);
        $rembourse = Reservation::factory()->annulee()->create();
        Paiement::factory()->for($rembourse)->rembourse()->create();

        $statuts = collect($this->getJson(URL_RESERVATIONS_ADMIN)->json('data'))->pluck('statutPaiement', 'id');
        expect($statuts[$sans->id])->toBeNull()->and($statuts[$reussi->id])->toBe('reussi')->and($statuts[$rembourse->id])->toBe('rembourse');
    });

    it('trie du plus récent au plus ancien et pagine par 20', function () {
        adminConnecte();
        foreach (range(1, 25) as $i) {
            Reservation::factory()->create(['created_at' => Carbon::parse('2030-01-01 00:00:00')->addMinutes($i)]);
        }

        $page1 = $this->getJson(URL_RESERVATIONS_ADMIN)->assertOk();
        expect($page1->json('data'))->toHaveCount(20)->and($page1->json('meta.total'))->toBe(25)
            ->and($page1->json('data.0.dateCreation'))->toBe('2030-01-01T00:25:00+00:00');
        expect($this->getJson(URL_RESERVATIONS_ADMIN.'?page=2')->json('data'))->toHaveCount(5);
    });

    it('filtre par statut', function () {
        adminConnecte();
        $attente = Reservation::factory()->enAttente()->create();
        $terminee = Reservation::factory()->terminee()->create();

        expect(idsReservationsAdmin('?statut=en_attente'))->toBe([$attente->id])
            ->and(idsReservationsAdmin('?statut=terminee'))->toBe([$terminee->id])
            ->and(idsReservationsAdmin('?statut=annulee'))->toBe([]);
    });

    it('filtre par prestataire et par client', function () {
        adminConnecte();
        $prestataire = Prestataire::factory()->create();
        $client = User::factory()->create();
        $a = reservationPourAdmin($prestataire, 'enAttente', ['client_id' => $client->id]);
        $b = reservationPourAdmin($prestataire);
        Reservation::factory()->create(['client_id' => $client->id]);

        expect(idsReservationsAdmin('?prestataire='.$prestataire->id))->toEqualCanonicalizing([$a->id, $b->id])
            ->and(idsReservationsAdmin('?client='.$client->id))->toHaveCount(2)
            ->and(idsReservationsAdmin("?prestataire={$prestataire->id}&client={$client->id}"))->toBe([$a->id]);
    });

    it('filtre par jour de créneau, bornes incluses', function () {
        adminConnecte();
        $prestataire = Prestataire::factory()->create();
        $avant = reservationPourAdmin($prestataire, 'enAttente', [], '2030-02-09 23:00:00');
        $debut = reservationPourAdmin($prestataire, 'enAttente', [], '2030-02-10 00:00:00');
        $fin = reservationPourAdmin($prestataire, 'enAttente', [], '2030-02-12 23:30:00');
        $apres = reservationPourAdmin($prestataire, 'enAttente', [], '2030-02-13 00:00:00');

        expect(idsReservationsAdmin('?debut=2030-02-10&fin=2030-02-12'))->toEqualCanonicalizing([$debut->id, $fin->id])
            ->and(idsReservationsAdmin('?debut=2030-02-13'))->toBe([$apres->id])
            ->and(idsReservationsAdmin('?fin=2030-02-09'))->toBe([$avant->id]);
    });

    it('cherche par code sans le renvoyer', function () {
        adminConnecte();
        $voulue = Reservation::factory()->confirmee()->create(['code_reservation' => 'AB12CD']);
        Reservation::factory()->confirmee()->create(['code_reservation' => 'ZZ99ZZ']);

        expect(idsReservationsAdmin('?q=ab12'))->toBe([$voulue->id])
            ->and(idsReservationsAdmin('?q=B12C'))->toBe([$voulue->id])
            ->and(idsReservationsAdmin('?q=XXXXXX'))->toBe([])
            ->and(strtolower($this->getJson(URL_RESERVATIONS_ADMIN.'?q=ab12')->getContent()))->not->toContain('ab12cd');
    });

    it('combine les filtres', function () {
        adminConnecte();
        $prestataire = Prestataire::factory()->create();
        $voulue = reservationPourAdmin($prestataire, 'confirmee', ['code_reservation' => 'QWE123'], '2030-03-05 09:00:00');
        reservationPourAdmin($prestataire, 'terminee', [], '2030-03-05 11:00:00');
        reservationPourAdmin($prestataire, 'confirmee', [], '2030-04-05 09:00:00');

        expect(idsReservationsAdmin("?statut=confirmee&prestataire={$prestataire->id}&debut=2030-03-01&fin=2030-03-31&q=qwe"))->toBe([$voulue->id]);
    });

    it('rejette des filtres invalides en français (422)', function (string $requete, string $champ, string $message) {
        adminConnecte();

        $this->getJson(URL_RESERVATIONS_ADMIN.$requete)->assertUnprocessable()->assertJsonPath("errors.$champ.0", $message);
    })->with([
        'statut' => ['?statut=inconnu', 'statut', 'Le statut doit être en_attente, acceptee, confirmee, en_cours, terminee, refusee ou annulee.'],
        'prestataire' => ['?prestataire=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'prestataire', 'Le nom du prestataire ne peut pas dépasser 100 caractères.'],
        'client' => ['?client=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'client', 'Le nom du client ne peut pas dépasser 100 caractères.'],
        'début' => ['?debut=hier', 'debut', 'La date de début du créneau doit être au format AAAA-MM-JJ.'],
        'fin' => ['?fin=10/01/2030', 'fin', 'La date de fin du créneau doit être au format AAAA-MM-JJ.'],
        'période inversée' => ['?debut=2030-02-01&fin=2030-01-01', 'fin', 'La date de fin doit être postérieure ou égale à la date de début.'],
        'page' => ['?page=0', 'page', 'La page doit être au moins 1.'],
        'code trop long' => ['?q=AAAAAAAAAAAAAAAAAAAAA', 'q', 'La recherche ne peut pas dépasser 20 caractères.'],
    ]);

    it('n\'expose ni code de réservation ni donnée sensible', function () {
        adminConnecte();
        $reservation = Reservation::factory()->confirmee()->create(['code_reservation' => 'KJ82LM']);
        Paiement::factory()->for($reservation)->reussi()->create(['reference_dexpay' => 'DXP-SECRET-REF', 'url_paiement' => 'https://paiement.exemple/secret-url']);

        $corps = strtolower($this->getJson(URL_RESERVATIONS_ADMIN)->assertOk()->getContent());

        foreach (['kj82lm', 'code', 'dxp-secret-ref', 'secret-url', 'referencedexpay', 'password', 'token', 'jeton', 'email', 'telephone', strtolower($reservation->client->password)] as $interdit) {
            expect($corps)->not->toContain($interdit);
        }
    });

    it('exécute un nombre de requêtes constant quel que soit le volume', function () {
        adminConnecte();
        $r = Reservation::factory()->confirmee()->create();
        Paiement::factory()->for($r)->reussi()->create();
        $petit = nombreDeRequetes(fn () => $this->getJson(URL_RESERVATIONS_ADMIN)->assertOk());

        foreach (range(1, 8) as $i) {
            $r = Reservation::factory()->confirmee()->create();
            Paiement::factory()->for($r)->reussi()->create();
            Paiement::factory()->for($r)->echoue()->create();
        }
        $grand = nombreDeRequetes(fn () => $this->getJson(URL_RESERVATIONS_ADMIN)->assertOk());

        expect($grand)->toBe($petit);
    });
});

describe('réservations — détail', function () {
    it('répond 404 pour un identifiant inconnu ou mal formé', function () {
        adminConnecte();

        foreach (['0198a1b2-0000-7000-8000-00000000ffff', 'pas-un-uuid'] as $id) {
            $this->getJson(URL_RESERVATIONS_ADMIN.'/'.$id)->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
        }
    });

    it('renvoie le détail exact avec paiement, avis, litige et historique', function () {
        adminConnecte();
        $client = User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $prestataire = Prestataire::factory()->create(['nom_entreprise' => 'Garage Exemple']);
        $prestation = Prestation::factory()->for($prestataire)->create(['nom' => 'Vidange', 'duree_minutes' => 60, 'prix' => 10000]);
        $creneau = Creneau::factory()->for($prestataire)->create(['date_debut' => '2030-01-21 09:00:00', 'date_fin' => '2030-01-21 10:00:00']);
        $reservation = Reservation::factory()->terminee()->create([
            'client_id' => $client->id, 'creneau_id' => $creneau->id, 'prestation_id' => $prestation->id, 'prix_applique' => '10000.00',
            'code_reservation' => 'KJ82LM', 'acceptee_le' => '2030-01-20 08:30:00', 'created_at' => '2030-01-20 08:00:00', 'updated_at' => '2030-01-21 10:05:00',
        ]);
        $paiement = Paiement::factory()->for($reservation)->reussi()->create([
            'montant_acompte' => '3000.00', 'commission' => '1000.00', 'paye_le' => '2030-01-20 09:00:00', 'credite_le' => '2030-01-21 10:05:00', 'reference_dexpay' => 'DXP-SECRET-REF',
        ]);
        $avis = Avis::factory()->note(4)->create(['reservation_id' => $reservation->id, 'created_at' => '2030-01-21 12:00:00']);
        $litige = Litige::factory()->create(['reservation_id' => $reservation->id, 'created_at' => '2030-01-21 13:00:00']);

        $reponse = $this->getJson(URL_RESERVATIONS_ADMIN.'/'.$reservation->id)->assertOk();
        $reponse->assertExactJson([
            'id' => $reservation->id, 'statut' => 'terminee',
            'prestataire' => ['id' => $prestataire->id, 'nomEntreprise' => 'Garage Exemple'],
            'prestation' => ['id' => $prestation->id, 'nom' => 'Vidange', 'dureeMinutes' => 60],
            'creneau' => ['id' => $creneau->id, 'debut' => '2030-01-21T09:00:00+00:00', 'fin' => '2030-01-21T10:00:00+00:00'],
            'prixApplique' => 10000,
            'client' => ['id' => $client->id, 'prenom' => 'Awa', 'nom' => 'Diallo'],
            'motifRefus' => null,
            'paiement' => [
                'id' => $paiement->id, 'statut' => 'reussi', 'montantAcompte' => 3000, 'commission' => 1000, 'remboursementDu' => false, 'remboursementDuLe' => null,
                'tentativesRemboursement' => 0, 'tentativesEpuisees' => false, 'payeLe' => '2030-01-20T09:00:00+00:00', 'rembourseLe' => null, 'crediteLe' => '2030-01-21T10:05:00+00:00', 'remboursementDerniereErreur' => null,
            ],
            'nombrePaiements' => 1,
            'avis' => ['id' => $avis->id, 'note' => 4, 'statut' => 'publie', 'dateCreation' => '2030-01-21T12:00:00+00:00'],
            'litige' => ['id' => $litige->id, 'statut' => 'ouvert', 'dateCreation' => '2030-01-21T13:00:00+00:00'],
            'historique' => [
                'creeeLe' => '2030-01-20T08:00:00+00:00', 'accepteeLe' => '2030-01-20T08:30:00+00:00', 'payeLe' => '2030-01-20T09:00:00+00:00',
                'remboursementDuLe' => null, 'rembourseLe' => null, 'debutCreneau' => '2030-01-21T09:00:00+00:00', 'finCreneau' => '2030-01-21T10:00:00+00:00',
                'misAJourLe' => '2030-01-21T10:05:00+00:00',
            ],
        ]);

        $corps = strtolower($reponse->getContent());
        foreach (['kj82lm', 'code', 'dxp-secret-ref', 'referencedexpay', 'urlpaiement', 'email', 'telephone', 'password', 'token', 'jeton'] as $interdit) {
            expect($corps)->not->toContain($interdit);
        }
    });

    it('renvoie paiement, avis et litige nuls quand il n\'y en a pas', function () {
        adminConnecte();
        $reservation = Reservation::factory()->refusee()->create();

        $this->getJson(URL_RESERVATIONS_ADMIN.'/'.$reservation->id)->assertOk()
            ->assertJsonPath('paiement', null)->assertJsonPath('avis', null)->assertJsonPath('litige', null)
            ->assertJsonPath('nombrePaiements', 0)->assertJsonPath('historique.payeLe', null);
    });

    it('montre un remboursement dû et ses tentatives', function () {
        adminConnecte();
        $reservation = Reservation::factory()->annulee()->create();
        Paiement::factory()->for($reservation)->reussi()->create(['remboursement_du_le' => '2030-01-20 10:00:00', 'remboursement_tentatives' => 4]);

        $this->getJson(URL_RESERVATIONS_ADMIN.'/'.$reservation->id)->assertOk()
            ->assertJsonPath('paiement.remboursementDu', true)
            ->assertJsonPath('paiement.tentativesRemboursement', 4)
            ->assertJsonPath('paiement.tentativesEpuisees', false)
            ->assertJsonPath('historique.remboursementDuLe', '2030-01-20T10:00:00+00:00');
    });

    it('exécute un nombre de requêtes constant quel que soit le volume', function () {
        adminConnecte();
        $reservation = Reservation::factory()->terminee()->create();
        Paiement::factory()->for($reservation)->reussi()->create();
        $petit = nombreDeRequetes(fn () => $this->getJson(URL_RESERVATIONS_ADMIN.'/'.$reservation->id)->assertOk());

        Avis::factory()->create(['reservation_id' => $reservation->id]);
        Litige::factory()->create(['reservation_id' => $reservation->id]);
        Paiement::factory()->for($reservation)->echoue()->count(3)->create();
        $grand = nombreDeRequetes(fn () => $this->getJson(URL_RESERVATIONS_ADMIN.'/'.$reservation->id)->assertOk());

        expect($grand)->toBe($petit);
    });
});

describe('réservations — filtres par nom', function () {
    it('filtre par nom du client ou du prestataire (recherche partielle), ou par identifiant', function () {
        adminConnecte();
        $garage = Prestataire::factory()->create(['nom_entreprise' => 'Garage Soleil']);
        $salon = Prestataire::factory()->create(['nom_entreprise' => 'Salon Lune']);
        $awa = User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $moussa = User::factory()->create(['prenom' => 'Moussa', 'nom' => 'Ba']);
        $r1 = reservationPourAdmin($garage, 'enAttente', ['client_id' => $awa->id]);
        $r2 = reservationPourAdmin($salon, 'enAttente', ['client_id' => $moussa->id]);

        expect(idsReservationsAdmin('?prestataire=soleil'))->toBe([$r1->id])
            ->and(idsReservationsAdmin('?client=Moussa'))->toBe([$r2->id])
            ->and(idsReservationsAdmin('?client=awa%20dial'))->toBe([$r1->id])
            ->and(idsReservationsAdmin('?prestataire='.$salon->id))->toBe([$r2->id])
            ->and(idsReservationsAdmin('?client=inconnu'))->toBe([]);
    });
});
