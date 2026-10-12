<?php

use App\Enums\StatutCompte;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function prestataireVisibleAgenda(StatutCompte $statut = StatutCompte::Actif): Prestataire
{
    return Prestataire::factory()->for(User::factory()->prestataire()->statut($statut), 'user')->create();
}

function urlAgendaClient(Prestataire|string $p, string $requete = ''): string
{
    return '/api/v1/prestataires/'.($p instanceof Prestataire ? $p->id : $p).'/agenda'.$requete;
}

function creneauClient(Prestataire $p, int $jours, string $heure, string $etat = 'libre'): Creneau
{
    $debut = now()->addDays($jours)->setTimeFromTimeString($heure);

    return Creneau::factory()->{$etat}()->for($p)->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addMinutes(60)]);
}

function agirCommeClientAgenda(): void
{
    test()->actingAs(User::factory()->create(), 'sanctum');
}

it('renvoie 401 sans jeton, puis 403 pour un prestataire et un administrateur', function () {
    $p = prestataireVisibleAgenda();
    $this->getJson(urlAgendaClient($p))->assertUnauthorized();

    foreach ([User::factory()->prestataire()->create(), User::factory()->administrateur()->create()] as $user) {
        $this->actingAs($user, 'sanctum');
        $this->getJson(urlAgendaClient($p))->assertForbidden();
    }
});

describe('contenu', function () {
    it('ne montre que les créneaux réservables, futurs, triés', function () {
        $p = prestataireVisibleAgenda();
        agirCommeClientAgenda();
        $tard = creneauClient($p, 3, '15:00');
        $tot = creneauClient($p, 2, '09:00');
        $expire = creneauClient($p, 2, '11:00', 'verrouille');
        $expire->forceFill(['verrou_expire_a' => now()->subMinute()])->save();
        creneauClient($p, 2, '12:00', 'verrouille');
        creneauClient($p, 2, '13:00', 'reserve');
        creneauClient($p, 2, '14:00', 'indisponible');
        creneauClient($p, -1, '09:00');

        $reponse = $this->getJson(urlAgendaClient($p))->assertOk();

        expect(array_column($reponse->json('data'), 'id'))->toBe([$tot->id, $expire->id, $tard->id]);
    });

    it('n\'expose ni statut ni donnée personnelle', function () {
        $p = prestataireVisibleAgenda();
        agirCommeClientAgenda();
        creneauClient($p, 2, '09:00');

        $reponse = $this->getJson(urlAgendaClient($p))->assertOk();

        expect(array_keys($reponse->json('data.0')))->toBe(['id', 'dateDebut', 'dateFin']);
        $brut = $reponse->getContent();
        foreach ([$p->user->email, $p->user->telephone, $p->id, 'statut', 'verrou'] as $interdit) {
            expect($brut)->not->toContain((string) $interdit);
        }
    });

    it('n\'affiche pas les créneaux d\'un autre prestataire', function () {
        $p = prestataireVisibleAgenda();
        creneauClient(prestataireVisibleAgenda(), 2, '09:00');
        agirCommeClientAgenda();

        $this->getJson(urlAgendaClient($p))->assertOk()->assertJsonCount(0, 'data');
    });

    it('envoie Cache-Control: no-store', function () {
        $p = prestataireVisibleAgenda();
        agirCommeClientAgenda();

        expect($this->getJson(urlAgendaClient($p))->assertOk()->headers->get('Cache-Control'))->toContain('no-store');
    });
});

describe('intervalle', function () {
    it('filtre par intervalle, bornes incluses', function () {
        $p = prestataireVisibleAgenda();
        agirCommeClientAgenda();
        creneauClient($p, 2, '23:00');
        creneauClient($p, 3, '10:00');
        creneauClient($p, 4, '10:00');

        $requete = '?du='.now()->addDays(2)->toDateString().'&au='.now()->addDays(3)->toDateString();
        $this->getJson(urlAgendaClient($p, $requete))->assertOk()->assertJsonCount(2, 'data');
    });

    it('prend 14 jours par défaut', function () {
        $p = prestataireVisibleAgenda();
        agirCommeClientAgenda();
        $dedans = creneauClient($p, 13, '10:00');
        creneauClient($p, 16, '10:00');

        $reponse = $this->getJson(urlAgendaClient($p))->assertOk();

        expect(array_column($reponse->json('data'), 'id'))->toBe([$dedans->id]);
    });

    it('refuse un intervalle trop long, un début passé et un intervalle inversé', function (string $requete, string $champ) {
        $p = prestataireVisibleAgenda();
        agirCommeClientAgenda();

        $this->getJson(urlAgendaClient($p, $requete))->assertUnprocessable()->assertJsonValidationErrors([$champ]);
    })->with([
        'plus de 31 jours' => [fn () => '?du='.now()->addDay()->toDateString().'&au='.now()->addDays(33)->toDateString(), 'au'],
        'début passé' => [fn () => '?du='.now()->subDay()->toDateString(), 'du'],
        'fin avant début' => [fn () => '?du='.now()->addDays(5)->toDateString().'&au='.now()->addDays(2)->toDateString(), 'au'],
        'format invalide' => [fn () => '?du=demain', 'du'],
    ]);

    it('accepte exactement 31 jours', function () {
        $p = prestataireVisibleAgenda();
        agirCommeClientAgenda();

        $this->getJson(urlAgendaClient($p, '?du='.now()->addDay()->toDateString().'&au='.now()->addDays(32)->toDateString()))->assertOk();
    });
});

describe('visibilité du prestataire', function () {
    it('répond 404 pour un compte non actif, un identifiant inconnu ou invalide', function (string $cas) {
        agirCommeClientAgenda();
        $cible = match ($cas) {
            'en attente' => prestataireVisibleAgenda(StatutCompte::EnAttenteValidation),
            'suspendu' => prestataireVisibleAgenda(StatutCompte::Suspendu),
            'inconnu' => fake()->uuid(),
            'invalide' => 'pas-un-uuid',
        };

        $this->getJson(urlAgendaClient($cible))->assertNotFound()->assertJsonPath('message', 'Prestataire introuvable.');
    })->with(['en attente', 'suspendu', 'inconnu', 'invalide']);
});
