<?php

use App\Models\Avis;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(User::factory()->create()));

function prestataireAvecNotes(string $nom, array $notes, float $prix = 10000): Prestataire
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create(['nom_entreprise' => $nom]);
    Prestation::factory()->create(['prestataire_id' => $prestataire->id, 'prix' => $prix]);

    foreach ($notes as $note) {
        $creneau = Creneau::factory()->for($prestataire)->reserve()->create();
        $reservation = Reservation::factory()->terminee()->create(['client_id' => User::factory()->create()->id, 'creneau_id' => $creneau->id]);
        Avis::factory()->note($note)->create(['reservation_id' => $reservation->id]);
    }

    return $prestataire;
}

function nomsRecherche(string $query = ''): array
{
    return collect(test()->getJson('/api/v1/prestataires'.$query)->assertOk()->json('data'))->pluck('nomEntreprise')->all();
}

describe('tri par note et filtre note_min', function () {
    it('trie par note moyenne décroissante, les prestataires sans avis en dernier', function () {
        prestataireAvecNotes('Sans avis', []);
        prestataireAvecNotes('Moyen', [3, 4]);
        prestataireAvecNotes('Excellent', [5, 5]);

        expect(nomsRecherche('?tri=note'))->toBe(['Excellent', 'Moyen', 'Sans avis']);
    });

    it('départage les égalités par nombre d\'avis puis par nom, de façon stable', function () {
        prestataireAvecNotes('Beta', [4]);
        prestataireAvecNotes('Alpha', [4]);
        prestataireAvecNotes('Gamma', [4, 4]);

        expect(nomsRecherche('?tri=note'))->toBe(['Gamma', 'Alpha', 'Beta']);
    });

    it('filtre par note minimale et exclut les prestataires sans avis', function () {
        prestataireAvecNotes('Sans avis', []);
        prestataireAvecNotes('Moyen', [3, 4]);
        prestataireAvecNotes('Excellent', [5, 4]);

        expect(nomsRecherche('?note_min=4'))->toBe(['Excellent'])
            ->and(nomsRecherche('?note_min=3.5&tri=note'))->toBe(['Excellent', 'Moyen'])
            ->and(nomsRecherche('?note_min=0'))->toBe(['Excellent', 'Moyen']);
    });

    it('ignore les avis masqués dans le tri et le filtre', function () {
        $p = prestataireAvecNotes('Masque', [5]);
        DB::table('avis')->where('prestataire_id', $p->id)->update([
            'masque_le' => now(), 'modere_le' => now(), 'modere_par_id' => User::factory()->administrateur()->create()->id,
        ]);

        expect(nomsRecherche('?note_min=1'))->toBe([]);
    });

    it('valide note_min et tri en français', function () {
        $this->getJson('/api/v1/prestataires?note_min=6')->assertStatus(422)->assertJsonPath('errors.note_min.0', 'La note minimale ne peut pas dépasser 5.');
        $this->getJson('/api/v1/prestataires?note_min=abc')->assertStatus(422)->assertJsonValidationErrors('note_min');
        $this->getJson('/api/v1/prestataires?tri=zzz')->assertStatus(422)->assertJsonValidationErrors('tri');
    });

    it('garde noteMoyenne et nombreAvis sur chaque ligne, sans requête par prestataire', function () {
        prestataireAvecNotes('A', [5, 4]);
        prestataireAvecNotes('B', []);
        prestataireAvecNotes('C', [3]);

        DB::enableQueryLog();
        $lignes = collect($this->getJson('/api/v1/prestataires?tri=note')->assertOk()->json('data'))->keyBy('nomEntreprise');
        $requetes = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($lignes['A']['noteMoyenne'])->toBe(4.5)->and($lignes['A']['nombreAvis'])->toBe(2)
            ->and($lignes['B']['noteMoyenne'])->toBeNull()->and($lignes['B']['nombreAvis'])->toBe(0);

        prestataireAvecNotes('D', [2]);
        prestataireAvecNotes('E', [1]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/prestataires?tri=note')->assertOk();
        expect(count(DB::getQueryLog()))->toBe($requetes);
        DB::disableQueryLog();
    });
});

describe('profil public : acompte', function () {
    it('expose le pourcentage d\'acompte de la configuration et l\'acompte de chaque prestation', function () {
        $p = prestataireAvecNotes('Garage', [5], 15000);
        Prestation::factory()->create(['prestataire_id' => $p->id, 'prix' => 5000, 'nom' => 'Zèbre']);

        $reponse = $this->getJson('/api/v1/prestataires/'.$p->id)->assertOk()
            ->assertJsonPath('acomptePourcentage', 30)
            ->assertJsonPath('noteMoyenne', 5)->assertJsonPath('nombreAvis', 1);
        $montants = collect($reponse->json('prestations'))->mapWithKeys(fn ($x) => [(int) $x['prix'] => $x['montantAcompte']]);

        expect($montants[15000])->toEqual(4500)->and($montants[5000])->toEqual(1500);
    });

    it('suit le taux de la configuration', function () {
        config(['sopi.paiement.acompte_taux' => 0.25]);
        $p = prestataireAvecNotes('Garage', [], 10000);

        $this->getJson('/api/v1/prestataires/'.$p->id)->assertOk()
            ->assertJsonPath('acomptePourcentage', 25)->assertJsonPath('prestations.0.montantAcompte', 2500);
    });
});
