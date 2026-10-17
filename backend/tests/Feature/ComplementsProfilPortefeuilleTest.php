<?php

use App\Enums\OperateurRetrait;
use App\Enums\StatutRetrait;
use App\Enums\TypeMouvementPortefeuille;
use App\Models\Creneau;
use App\Models\MouvementPortefeuille;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\Retrait;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function prestataireComplements(array $attributs = []): Prestataire
{
    return Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create($attributs);
}

describe('GET /me — bloc prestataire', function () {
    it('ajoute le bloc prestataire pour un prestataire, sans modifier les champs existants', function () {
        $prestataire = prestataireComplements(['nom_entreprise' => 'Salon Fatou', 'metier' => 'Coiffure', 'quartier' => 'Keur Massar Nord']);

        $this->actingAs($prestataire->user, 'sanctum')->getJson('/api/v1/me')
            ->assertOk()
            ->assertExactJsonStructure(['id', 'nom', 'prenom', 'email', 'telephone', 'role', 'statutCompte', 'dateCreation', 'prestataire' => ['id', 'nomEntreprise', 'metier', 'quartier']])
            ->assertJsonPath('id', $prestataire->user->id)
            ->assertJsonPath('prestataire.id', $prestataire->id)
            ->assertJsonPath('prestataire.nomEntreprise', 'Salon Fatou')
            ->assertJsonPath('prestataire.metier', 'Coiffure')
            ->assertJsonPath('prestataire.quartier', 'Keur Massar Nord');
    });

    it('a la même forme que le profil public du prestataire', function () {
        $prestataire = prestataireComplements();
        $client = User::factory()->create();

        $public = $this->actingAs($client, 'sanctum')->getJson("/api/v1/prestataires/{$prestataire->id}")->json();
        $moi = $this->actingAs($prestataire->user, 'sanctum')->getJson('/api/v1/me')->json('prestataire');

        expect($moi)->toBe(array_intersect_key($public, array_flip(['id', 'nomEntreprise', 'metier', 'quartier'])));
    });

    it('omet le bloc pour un client et pour un administrateur', function () {
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/me')->assertOk()->assertJsonMissingPath('prestataire');
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')->getJson('/api/v1/me')->assertOk()->assertJsonMissingPath('prestataire');
    });

    it('renvoie le bloc de son propre compte seulement', function () {
        $autre = prestataireComplements(['nom_entreprise' => 'Autre atelier']);
        $moi = prestataireComplements(['nom_entreprise' => 'Mon atelier']);

        $this->actingAs($moi->user, 'sanctum')->getJson('/api/v1/me')
            ->assertJsonPath('prestataire.id', $moi->id)
            ->assertJsonPath('prestataire.nomEntreprise', 'Mon atelier');
        expect($autre->id)->not->toBe($moi->id);
    });
});

describe('GET /prestataire/portefeuille/mouvements — prestation, opérateur, statut du retrait', function () {
    it('un crédit porte le nom du service, sans opérateur ni statut de retrait', function () {
        $prestataire = prestataireComplements();
        $prestation = Prestation::factory()->for($prestataire)->create(['nom' => 'Coupe femme']);
        $reservation = Reservation::factory()->terminee()->create([
            'client_id' => User::factory()->create()->id,
            'creneau_id' => Creneau::factory()->for($prestataire)->reserve()->create()->id,
            'prestation_id' => $prestation->id,
        ]);
        MouvementPortefeuille::enregistrer($prestataire->id, TypeMouvementPortefeuille::Credit, 1000, 'Prestation terminée (acompte moins commission)', 1000, $reservation->id);

        $this->actingAs($prestataire->user, 'sanctum')->getJson('/api/v1/prestataire/portefeuille/mouvements')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'credit')
            ->assertJsonPath('data.0.libelle', 'Prestation terminée (acompte moins commission)')
            ->assertJsonPath('data.0.montant', 1000)
            ->assertJsonPath('data.0.prestation', 'Coupe femme')
            ->assertJsonPath('data.0.operateur', null)
            ->assertJsonPath('data.0.statutRetrait', null);
    });

    it('un retrait porte l\'opérateur et le statut, sans prestation, pour les trois statuts', function (StatutRetrait $statut, OperateurRetrait $operateur) {
        $prestataire = prestataireComplements();
        $retrait = Retrait::factory()->for($prestataire)->create(['statut' => $statut, 'operateur' => $operateur]);
        MouvementPortefeuille::enregistrer($prestataire->id, TypeMouvementPortefeuille::Retrait, 5000, 'Retrait', 0, null, $retrait->id);

        $this->actingAs($prestataire->user, 'sanctum')->getJson('/api/v1/prestataire/portefeuille/mouvements')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'retrait')
            ->assertJsonPath('data.0.retraitId', $retrait->id)
            ->assertJsonPath('data.0.prestation', null)
            ->assertJsonPath('data.0.operateur', $operateur->value)
            ->assertJsonPath('data.0.statutRetrait', $statut->value);
    })->with([
        'en cours' => [StatutRetrait::EnCours, OperateurRetrait::Wave],
        'réussi' => [StatutRetrait::Reussi, OperateurRetrait::OrangeMoney],
        'échoué' => [StatutRetrait::Echoue, OperateurRetrait::Wave],
    ]);

    it('un retrait sans opérateur connu donne operateur null', function () {
        $prestataire = prestataireComplements();
        $retrait = Retrait::factory()->for($prestataire)->create(['operateur' => null]);
        MouvementPortefeuille::enregistrer($prestataire->id, TypeMouvementPortefeuille::Retrait, 5000, 'Retrait', 0, null, $retrait->id);

        $this->actingAs($prestataire->user, 'sanctum')->getJson('/api/v1/prestataire/portefeuille/mouvements')
            ->assertJsonPath('data.0.operateur', null)
            ->assertJsonPath('data.0.statutRetrait', 'en_cours');
    });

    it('ne montre que ses propres mouvements', function () {
        $autre = prestataireComplements();
        $moi = prestataireComplements();
        MouvementPortefeuille::enregistrer($autre->id, TypeMouvementPortefeuille::Credit, 900, 'Autre', 900);
        MouvementPortefeuille::enregistrer($moi->id, TypeMouvementPortefeuille::Credit, 100, 'Moi', 100);

        $this->actingAs($moi->user, 'sanctum')->getJson('/api/v1/prestataire/portefeuille/mouvements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.libelle', 'Moi');
    });

    it('charge les relations à l\'avance : le nombre de requêtes ne dépend pas du nombre de mouvements', function () {
        $prestataire = prestataireComplements();
        $prestation = Prestation::factory()->for($prestataire)->create();
        foreach (range(1, 6) as $i) {
            $reservation = Reservation::factory()->terminee()->create([
                'client_id' => User::factory()->create()->id,
                'creneau_id' => Creneau::factory()->for($prestataire)->reserve()->create()->id,
                'prestation_id' => $prestation->id,
            ]);
            MouvementPortefeuille::enregistrer($prestataire->id, TypeMouvementPortefeuille::Credit, 100, 'Crédit', 100 * $i, $reservation->id);
            $retrait = Retrait::factory()->for($prestataire)->create();
            MouvementPortefeuille::enregistrer($prestataire->id, TypeMouvementPortefeuille::Retrait, 50, 'Retrait', 50, null, $retrait->id);
        }
        $this->actingAs($prestataire->user, 'sanctum');

        DB::enableQueryLog();
        $this->getJson('/api/v1/prestataire/portefeuille/mouvements')->assertOk()->assertJsonCount(12, 'data');
        $requetes = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Authentification, comptage, page, puis une requête par relation : bien moins que 12 mouvements × 2.
        expect($requetes)->toBeLessThan(12);
    });
});

describe('retraits — opérateur', function () {
    it('GET /prestataire/retraits expose l\'opérateur, les autres champs sont inchangés', function () {
        $prestataire = prestataireComplements();
        Retrait::factory()->for($prestataire)->reussi()->create(['operateur' => OperateurRetrait::OrangeMoney]);

        $this->actingAs($prestataire->user, 'sanctum')->getJson('/api/v1/prestataire/retraits')
            ->assertOk()
            ->assertJsonPath('data.0.operateur', 'orange_money')
            ->assertJsonPath('data.0.statut', 'reussi')
            ->assertJsonStructure(['data' => [['id', 'montant', 'statut', 'operateur', 'telephoneVersement', 'dateDemande', 'dateTraitement']]]);
    });

    it('GET /prestataire/retraits/{id} expose l\'opérateur', function () {
        $prestataire = prestataireComplements();
        $retrait = Retrait::factory()->for($prestataire)->create(['operateur' => OperateurRetrait::Wave]);

        $this->actingAs($prestataire->user, 'sanctum')->getJson("/api/v1/prestataire/retraits/{$retrait->id}")
            ->assertOk()->assertJsonPath('operateur', 'wave');
    });

    it('POST /prestataire/retraits renvoie l\'opérateur demandé', function (string $operateur) {
        $prestataire = Prestataire::factory()->avoirSolde(20000)->for(User::factory()->prestataire(), 'user')->create();

        $this->actingAs($prestataire->user, 'sanctum')
            ->postJson('/api/v1/prestataire/retraits', ['montant' => 5000, 'telephone_versement' => '771234567', 'operateur' => $operateur])
            ->assertCreated()
            ->assertJsonPath('operateur', $operateur);
    })->with(['wave', 'orange_money']);

    it('ne montre pas les retraits d\'un autre prestataire', function () {
        $autre = prestataireComplements();
        $moi = prestataireComplements();
        $retraitAutre = Retrait::factory()->for($autre)->create();

        $this->actingAs($moi->user, 'sanctum')->getJson('/api/v1/prestataire/retraits')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($moi->user, 'sanctum')->getJson("/api/v1/prestataire/retraits/{$retraitAutre->id}")->assertNotFound();
    });
});
