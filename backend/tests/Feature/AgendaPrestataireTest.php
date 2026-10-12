<?php

use App\Enums\StatutCompte;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const URL_AGENDA = '/api/v1/prestataire/agenda';
const URL_PLAGES = '/api/v1/prestataire/agenda/plages';

function prestataireAgenda(): Prestataire
{
    return Prestataire::factory()->for(User::factory()->prestataire()->statut(StatutCompte::Actif), 'user')->create();
}

function agirCommeAgenda(Prestataire $prestataire): void
{
    test()->actingAs($prestataire->user, 'sanctum');
}

function jourFutur(int $jours = 3): string
{
    return now()->addDays($jours)->toDateString();
}

function plage(array $surcharge = []): array
{
    return array_merge([
        'date' => jourFutur(),
        'heure_debut' => '09:00',
        'heure_fin' => '12:00',
        'duree_creneau_minutes' => 60,
    ], $surcharge);
}

function creneauA(Prestataire $prestataire, string $jour, string $debut, string $fin, string $etat = 'libre'): Creneau
{
    return Creneau::factory()->{$etat}()->for($prestataire)->create([
        'date_debut' => "$jour $debut:00",
        'date_fin' => "$jour $fin:00",
    ]);
}

describe('accès', function () {
    it('renvoie 401 sans jeton, puis 403 pour un client et un administrateur', function () {
        $this->postJson(URL_PLAGES, ['plages' => [plage()]])->assertUnauthorized();
        $this->getJson(URL_AGENDA)->assertUnauthorized();

        foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum');
            $this->postJson(URL_PLAGES, ['plages' => [plage()]])->assertForbidden();
            $this->getJson(URL_AGENDA)->assertForbidden();
            $this->deleteJson(URL_AGENDA.'/creneaux/'.fake()->uuid())->assertForbidden();
        }
        expect(Creneau::count())->toBe(0);
    });
});

describe('configuration', function () {
    it('crée les créneaux libres consécutifs d\'une plage', function () {
        $p = prestataireAgenda();
        agirCommeAgenda($p);

        $reponse = $this->postJson(URL_PLAGES, ['plages' => [plage()]])->assertCreated()->assertJsonPath('nombreCreneaux', 3);

        expect($reponse->json('creneaux'))->toHaveCount(3);
        $reponse->assertJsonPath('creneaux.0.statut', 'libre')->assertJsonStructure(['creneaux' => [['id', 'dateDebut', 'dateFin', 'statut']]]);
        expect(Creneau::orderBy('date_debut')->get()->map(fn ($c) => $c->date_debut->format('Y-m-d H:i').'>'.$c->date_fin->format('H:i'))->all())->toBe([
            jourFutur().' 09:00>10:00', jourFutur().' 10:00>11:00', jourFutur().' 11:00>12:00',
        ]);
        expect(Creneau::where('prestataire_id', $p->id)->count())->toBe(3);
    });

    it('crée plusieurs plages', function () {
        agirCommeAgenda(prestataireAgenda());

        $this->postJson(URL_PLAGES, ['plages' => [
            plage(),
            plage(['date' => jourFutur(4), 'heure_debut' => '14:00', 'heure_fin' => '15:00', 'duree_creneau_minutes' => 30]),
        ]])->assertCreated()->assertJsonPath('nombreCreneaux', 5);
    });

    it('répète la plage sur les semaines suivantes, le même jour de la semaine', function () {
        agirCommeAgenda(prestataireAgenda());

        $this->postJson(URL_PLAGES, ['plages' => [plage(['heure_fin' => '10:00', 'repeter_semaines' => 2])]])
            ->assertCreated()->assertJsonPath('nombreCreneaux', 3);

        expect(Creneau::orderBy('date_debut')->get()->map(fn ($c) => $c->date_debut->toDateString())->all())->toBe([
            jourFutur(), jourFutur(10), jourFutur(17),
        ]);
    });

    it('ne renvoie pas la liste au-delà de 200 créneaux', function () {
        agirCommeAgenda(prestataireAgenda());

        $reponse = $this->postJson(URL_PLAGES, ['plages' => [plage(['heure_debut' => '00:00', 'heure_fin' => '23:45', 'duree_creneau_minutes' => 15, 'repeter_semaines' => 0])]])
            ->assertCreated();
        expect($reponse->json('nombreCreneaux'))->toBe(95)->and($reponse->json())->toHaveKey('creneaux');

        $this->postJson(URL_PLAGES, ['plages' => [plage(['date' => jourFutur(5), 'heure_debut' => '00:00', 'heure_fin' => '23:45', 'duree_creneau_minutes' => 15, 'repeter_semaines' => 3])]])
            ->assertCreated()->assertJsonPath('nombreCreneaux', 380)->assertJsonMissingPath('creneaux');
    });

    it('refuse une date passée', function () {
        agirCommeAgenda(prestataireAgenda());

        $this->postJson(URL_PLAGES, ['plages' => [plage(['date' => now()->subDay()->toDateString()])]])
            ->assertUnprocessable()->assertJsonValidationErrors(['plages.0.date']);
        expect(Creneau::count())->toBe(0);
    });

    it('refuse une heure de fin avant l\'heure de début', function () {
        agirCommeAgenda(prestataireAgenda());

        $this->postJson(URL_PLAGES, ['plages' => [plage(['heure_debut' => '12:00', 'heure_fin' => '09:00'])]])
            ->assertUnprocessable()->assertJsonValidationErrors(['plages.0.heure_fin']);
    });

    it('refuse une plage qui n\'est pas un multiple de la durée', function () {
        agirCommeAgenda(prestataireAgenda());

        $this->postJson(URL_PLAGES, ['plages' => [plage(['duree_creneau_minutes' => 50])]])
            ->assertUnprocessable()->assertJsonValidationErrors(['plages.0.duree_creneau_minutes']);
        expect(Creneau::count())->toBe(0);
    });

    it('refuse les bornes dépassées', function (array $corps, string $champ) {
        agirCommeAgenda(prestataireAgenda());

        $this->postJson(URL_PLAGES, $corps)->assertUnprocessable()->assertJsonValidationErrors([$champ]);
        expect(Creneau::count())->toBe(0);
    })->with([
        'durée trop courte' => [fn () => ['plages' => [plage(['duree_creneau_minutes' => 10])]], 'plages.0.duree_creneau_minutes'],
        'durée trop longue' => [fn () => ['plages' => [plage(['heure_debut' => '00:00', 'heure_fin' => '16:00', 'duree_creneau_minutes' => 500])]], 'plages.0.duree_creneau_minutes'],
        'trop de semaines' => [fn () => ['plages' => [plage(['repeter_semaines' => 9])]], 'plages.0.repeter_semaines'],
        'semaines négatives' => [fn () => ['plages' => [plage(['repeter_semaines' => -1])]], 'plages.0.repeter_semaines'],
        'aucune plage' => [fn () => ['plages' => []], 'plages'],
        'plages absentes' => [fn () => [], 'plages'],
        'plus de 7 plages' => [fn () => ['plages' => array_map(fn ($i) => plage(['date' => jourFutur($i + 1)]), range(1, 8))], 'plages'],
        'plus de 500 créneaux' => [fn () => ['plages' => [plage(['heure_debut' => '00:00', 'heure_fin' => '23:45', 'duree_creneau_minutes' => 15, 'repeter_semaines' => 8])]], 'plages'],
    ]);
});

describe('chevauchements', function () {
    it('refuse un chevauchement avec un créneau existant, quel que soit son statut, sans rien créer', function (string $etat) {
        $p = prestataireAgenda();
        agirCommeAgenda($p);
        creneauA($p, jourFutur(), '10:30', '11:30', $etat);

        $reponse = $this->postJson(URL_PLAGES, ['plages' => [plage()]])->assertUnprocessable()->assertJsonValidationErrors(['plages']);

        expect($reponse->json('errors.plages'))->not->toBeEmpty()
            ->and(Creneau::count())->toBe(1);
    })->with(['libre', 'verrouille', 'reserve']);

    it('accepte un créneau qui touche seulement la limite d\'un créneau existant', function () {
        $p = prestataireAgenda();
        agirCommeAgenda($p);
        creneauA($p, jourFutur(), '12:00', '13:00');

        $this->postJson(URL_PLAGES, ['plages' => [plage()]])->assertCreated();
        expect(Creneau::count())->toBe(4);
    });

    it('ignore les créneaux d\'un autre prestataire', function () {
        creneauA(prestataireAgenda(), jourFutur(), '09:00', '10:00');
        agirCommeAgenda(prestataireAgenda());

        $this->postJson(URL_PLAGES, ['plages' => [plage()]])->assertCreated();
    });

    it('refuse un chevauchement entre deux plages de la même requête, sans rien créer', function () {
        agirCommeAgenda(prestataireAgenda());

        $this->postJson(URL_PLAGES, ['plages' => [
            plage(),
            plage(['heure_debut' => '11:00', 'heure_fin' => '12:00']),
        ]])->assertUnprocessable()->assertJsonValidationErrors(['plages']);

        expect(Creneau::count())->toBe(0);
    });
});

describe('consultation', function () {
    it('liste mes créneaux triés, sur 30 jours par défaut', function () {
        $p = prestataireAgenda();
        agirCommeAgenda($p);
        creneauA($p, jourFutur(5), '10:00', '11:00');
        creneauA($p, jourFutur(2), '10:00', '11:00', 'reserve');
        creneauA($p, jourFutur(40), '10:00', '11:00');

        $reponse = $this->getJson(URL_AGENDA)->assertOk()->assertJsonCount(2, 'data');

        $reponse->assertJsonPath('data.0.statut', 'reserve')->assertJsonStructure(['data' => [['id', 'dateDebut', 'dateFin', 'statut']]]);
        expect($reponse->json('data.0.dateDebut'))->toStartWith(jourFutur(2));
    });

    it('filtre par intervalle, bornes incluses', function () {
        $p = prestataireAgenda();
        agirCommeAgenda($p);
        creneauA($p, jourFutur(5), '23:00', '23:45');
        creneauA($p, jourFutur(6), '10:00', '11:00');
        creneauA($p, jourFutur(7), '10:00', '11:00');

        $this->getJson(URL_AGENDA.'?du='.jourFutur(5).'&au='.jourFutur(6))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(URL_AGENDA.'?du='.jourFutur(7).'&au='.jourFutur(7))->assertOk()->assertJsonCount(1, 'data');
    });

    it('refuse un intervalle de plus de 62 jours', function () {
        agirCommeAgenda(prestataireAgenda());

        $this->getJson(URL_AGENDA.'?du='.jourFutur(1).'&au='.jourFutur(64))->assertUnprocessable()->assertJsonValidationErrors(['au']);
        $this->getJson(URL_AGENDA.'?du='.jourFutur(1).'&au='.jourFutur(63))->assertOk();
        $this->getJson(URL_AGENDA.'?du='.jourFutur(5).'&au='.jourFutur(2))->assertUnprocessable();
        $this->getJson(URL_AGENDA.'?du=pas-une-date')->assertUnprocessable()->assertJsonValidationErrors(['du']);
    });

    it('ne montre pas les créneaux d\'un autre prestataire', function () {
        creneauA(prestataireAgenda(), jourFutur(), '09:00', '10:00');
        $moi = prestataireAgenda();
        agirCommeAgenda($moi);
        creneauA($moi, jourFutur(), '11:00', '12:00');

        $this->getJson(URL_AGENDA)->assertOk()->assertJsonCount(1, 'data');
    });
});

describe('suppression', function () {
    it('supprime un créneau libre', function () {
        $p = prestataireAgenda();
        agirCommeAgenda($p);
        $c = creneauA($p, jourFutur(), '09:00', '10:00');

        $this->deleteJson(URL_AGENDA.'/creneaux/'.$c->id)->assertNoContent();
        expect(Creneau::count())->toBe(0);
    });

    it('refuse de supprimer un créneau verrouillé ou réservé', function (string $etat) {
        $p = prestataireAgenda();
        agirCommeAgenda($p);
        $c = creneauA($p, jourFutur(), '09:00', '10:00', $etat);

        $this->deleteJson(URL_AGENDA.'/creneaux/'.$c->id)->assertStatus(409)->assertJsonPath('message', 'Ce créneau n\'est plus libre.');
        expect(Creneau::count())->toBe(1);
    })->with(['verrouille', 'reserve']);

    it('répond 404 pour le créneau d\'un autre prestataire ou inexistant', function () {
        $autre = creneauA(prestataireAgenda(), jourFutur(), '09:00', '10:00');
        agirCommeAgenda(prestataireAgenda());

        $this->deleteJson(URL_AGENDA.'/creneaux/'.$autre->id)->assertNotFound();
        $this->deleteJson(URL_AGENDA.'/creneaux/'.fake()->uuid())->assertNotFound();
        expect(Creneau::count())->toBe(1);
    });
});
