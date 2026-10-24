<?php

use App\Enums\StatutCompte;
use App\Enums\StatutCreneau;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function creneauVerrou(string $etat = 'libre', StatutCompte $statut = StatutCompte::Actif, int $jours = 2): Creneau
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire()->statut($statut), 'user')->create();
    $debut = now()->addDays($jours)->startOfHour();

    return Creneau::factory()->{$etat}()->for($prestataire)->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addHour()]);
}

/** Verrou posé directement en base pour un client, expirant dans $minutes (négatif = déjà expiré). */
function verrouPose(Creneau $c, User $client, int $minutes = 5): Creneau
{
    $c->forceFill(['statut' => StatutCreneau::Verrouille, 'verrouille_par' => $client->id, 'verrou_expire_a' => now()->addMinutes($minutes)])->save();

    return $c->fresh();
}

function urlVerrou(Creneau|string $c): string
{
    return '/api/v1/creneaux/'.($c instanceof Creneau ? $c->id : $c).'/verrou';
}

describe('accès', function () {
    it('renvoie 401 sans jeton, puis 403 pour un prestataire et un administrateur', function () {
        $c = creneauVerrou();
        $this->postJson(urlVerrou($c))->assertUnauthorized();
        $this->deleteJson(urlVerrou($c))->assertUnauthorized();

        foreach ([User::factory()->prestataire()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum');
            $this->postJson(urlVerrou($c))->assertForbidden();
            $this->deleteJson(urlVerrou($c))->assertForbidden();
        }
        expect($c->fresh()->statut)->toBe(StatutCreneau::Libre);
    });

    it('limite à 20 requêtes par minute et par utilisateur', function () {
        $this->actingAs(User::factory()->create(), 'sanctum');

        foreach (range(1, 20) as $i) {
            $this->deleteJson(urlVerrou(fake()->uuid()))->assertNotFound();
        }
        $this->deleteJson(urlVerrou(fake()->uuid()))->assertStatus(429);

        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->deleteJson(urlVerrou(fake()->uuid()))->assertNotFound();
    });
});

describe('verrouillage', function () {
    it('verrouille un créneau libre pour la durée configurée', function () {
        $client = User::factory()->create();
        $c = creneauVerrou();
        $this->actingAs($client, 'sanctum');

        $reponse = $this->postJson(urlVerrou($c))->assertOk()->assertJsonStructure(['creneauId', 'verrouExpireA'])->assertJsonPath('creneauId', $c->id);

        $c->refresh();
        expect($reponse->json('verrouExpireA'))->toBe($c->verrou_expire_a->toIso8601String())
            ->and($c->statut)->toBe(StatutCreneau::Verrouille)
            ->and($c->verrouille_par)->toBe($client->id)
            ->and($c->verrou_expire_a->between(now()->addMinutes(5)->subSeconds(10), now()->addMinutes(5)->addSeconds(10)))->toBeTrue();
    });

    it('utilise la durée de la configuration (SLOT_LOCK_MINUTES)', function () {
        config(['sopi.creneaux.verrou_minutes' => 2]);
        $c = creneauVerrou();
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(urlVerrou($c))->assertOk();

        expect($c->fresh()->verrou_expire_a->between(now()->addMinute(), now()->addMinutes(2)->addSeconds(10)))->toBeTrue();
    });

    it('refuse en 409 un créneau verrouillé par un autre, réservé ou indisponible', function (string $etat) {
        $c = creneauVerrou($etat);
        if ($etat === 'verrouille') {
            $c = verrouPose($c, User::factory()->create());
        }
        $avant = $c->fresh()->only(['statut', 'verrou_expire_a', 'verrouille_par']);
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(urlVerrou($c))->assertStatus(409)->assertJsonPath('message', 'Ce créneau n\'est plus disponible.');

        expect($c->fresh()->only(['statut', 'verrou_expire_a', 'verrouille_par']))->toEqual($avant);
    })->with(['verrouille', 'reserve', 'indisponible']);

    it('reprend un verrou expiré pour un autre client', function () {
        $nouveau = User::factory()->create();
        $c = verrouPose(creneauVerrou(), User::factory()->create(), -1);
        $this->actingAs($nouveau, 'sanctum');

        $this->postJson(urlVerrou($c))->assertOk();

        expect($c->fresh()->verrouille_par)->toBe($nouveau->id);
    });

    it('répond 404 pour un créneau passé, un prestataire non actif, un id inconnu ou invalide', function (string $cas) {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $cible = match ($cas) {
            'passé' => creneauVerrou(jours: -1),
            'prestataire en attente' => creneauVerrou(statut: StatutCompte::EnAttenteValidation),
            'prestataire suspendu' => creneauVerrou(statut: StatutCompte::Suspendu),
            'inconnu' => fake()->uuid(),
            'invalide' => 'pas-un-uuid',
        };

        $this->postJson(urlVerrou($cible))->assertNotFound()->assertJsonPath('message', 'Créneau introuvable.');
        if ($cible instanceof Creneau) {
            expect($cible->fresh()->statut)->toBe(StatutCreneau::Libre);
        }
    })->with(['passé', 'prestataire en attente', 'prestataire suspendu', 'inconnu', 'invalide']);

    it('est idempotent pour le même client, sans prolonger le verrou', function () {
        $c = creneauVerrou();
        $this->actingAs(User::factory()->create(), 'sanctum');

        $premier = $this->postJson(urlVerrou($c))->assertOk()->json('verrouExpireA');
        $this->travel(2)->minutes();
        $second = $this->postJson(urlVerrou($c))->assertOk()->json('verrouExpireA');

        expect($second)->toBe($premier);
    });

    it('ne laisse qu\'un verrou valide par client : le précédent est libéré', function () {
        $premier = creneauVerrou();
        $second = creneauVerrou(jours: 3);
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(urlVerrou($premier))->assertOk();
        $this->postJson(urlVerrou($second))->assertOk();

        expect($premier->fresh()->statut)->toBe(StatutCreneau::Libre)
            ->and($premier->fresh()->verrouille_par)->toBeNull()
            ->and($premier->fresh()->verrou_expire_a)->toBeNull()
            ->and($second->fresh()->statut)->toBe(StatutCreneau::Verrouille);
    });

    it('conserve le premier verrou si le second créneau est refusé', function () {
        $client = User::factory()->create();
        $premier = creneauVerrou();
        $pris = verrouPose(creneauVerrou(jours: 3), User::factory()->create());
        $this->actingAs($client, 'sanctum');

        $this->postJson(urlVerrou($premier))->assertOk();
        $this->postJson(urlVerrou($pris))->assertStatus(409);

        expect($premier->fresh()->verrouille_par)->toBe($client->id);
    });

    it('n\'expose aucune donnée personnelle', function () {
        $client = User::factory()->create();
        $c = creneauVerrou();
        $this->actingAs($client, 'sanctum');

        $brut = $this->postJson(urlVerrou($c))->assertOk()->getContent();

        foreach ([$client->id, $client->email, $client->telephone, $c->prestataire_id] as $interdit) {
            expect($brut)->not->toContain((string) $interdit);
        }
    });
});

describe('libération', function () {
    it('permet au propriétaire de libérer son verrou', function () {
        $client = User::factory()->create();
        $c = verrouPose(creneauVerrou(), $client);
        $this->actingAs($client, 'sanctum');

        $this->deleteJson(urlVerrou($c))->assertNoContent();

        expect($c->fresh()->statut)->toBe(StatutCreneau::Libre)
            ->and($c->fresh()->verrouille_par)->toBeNull()
            ->and($c->fresh()->verrou_expire_a)->toBeNull();
    });

    it('répond 404 pour le verrou d\'un autre, un créneau libre, inconnu ou invalide', function () {
        $c = verrouPose(creneauVerrou(), User::factory()->create());
        $libre = creneauVerrou(jours: 3);
        $this->actingAs(User::factory()->create(), 'sanctum');

        foreach ([$c->id, $libre->id, fake()->uuid(), 'pas-un-uuid'] as $cible) {
            $this->deleteJson(urlVerrou($cible))->assertNotFound()->assertJsonPath('message', 'Verrou introuvable.');
        }
        expect($c->fresh()->statut)->toBe(StatutCreneau::Verrouille);
    });
});

describe('modèle Creneau', function () {
    it('marquerReserve réussit pour le propriétaire avant expiration', function () {
        $client = User::factory()->create();
        $c = verrouPose(creneauVerrou(), $client);

        expect($c->marquerReserve($client->id))->toBeTrue();

        $c->refresh();
        expect($c->statut)->toBe(StatutCreneau::Reserve)->and($c->verrou_expire_a)->toBeNull()->and($c->verrouille_par)->toBeNull();
    });

    it('marquerReserve est refusé après expiration', function () {
        $client = User::factory()->create();
        $c = verrouPose(creneauVerrou(), $client, -1);

        expect($c->marquerReserve($client->id))->toBeFalse()
            ->and($c->fresh()->statut)->toBe(StatutCreneau::Verrouille);
    });

    it('marquerReserve est refusé pour un autre client ou un créneau libre', function () {
        $c = verrouPose(creneauVerrou(), User::factory()->create());

        expect($c->marquerReserve(User::factory()->create()->id))->toBeFalse()
            ->and(creneauVerrou()->marquerReserve(User::factory()->create()->id))->toBeFalse()
            ->and($c->fresh()->statut)->toBe(StatutCreneau::Verrouille);
    });

    it('libererVerrousExpires garde le dernier détenteur ; verrouExpireDuClient ne reconnaît que le verrou expiré du client', function () {
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $perime = verrouPose(creneauVerrou(), $a, -1);
        $valide = verrouPose(creneauVerrou(jours: 3), $a);
        $libre = creneauVerrou(jours: 4);
        $reserve = creneauVerrou('reserve', jours: 5);

        // Verrou périmé mais pas encore balayé : reconnu pour son détenteur seulement.
        expect($perime->verrouExpireDuClient($a->id))->toBeTrue()
            ->and($perime->verrouExpireDuClient($b->id))->toBeFalse()
            ->and($valide->verrouExpireDuClient($a->id))->toBeFalse()
            ->and($libre->verrouExpireDuClient($a->id))->toBeFalse()
            ->and($reserve->verrouExpireDuClient($a->id))->toBeFalse();

        expect(Creneau::libererVerrousExpires())->toBe(1);

        // Après le balayage : créneau libre, échéance vidée, mais le dernier détenteur reste reconnu.
        $perime = $perime->fresh();
        expect($perime->statut)->toBe(StatutCreneau::Libre)
            ->and($perime->verrou_expire_a)->toBeNull()
            ->and($perime->verrouille_par)->toBe($a->id)
            ->and($perime->verrouExpireDuClient($a->id))->toBeTrue()
            ->and($perime->verrouExpireDuClient($b->id))->toBeFalse()
            ->and($valide->fresh()->statut)->toBe(StatutCreneau::Verrouille);
    });

    it('verrouillerSiLibre n\'exécute qu\'une seule requête UPDATE, sans lecture préalable', function () {
        $c = creneauVerrou();
        $client = User::factory()->create();
        $requetes = [];
        DB::listen(function ($q) use (&$requetes) {
            $requetes[] = strtolower(ltrim($q->sql));
        });

        expect($c->verrouillerSiLibre(300, $client->id))->toBeTrue();

        $mises = array_values(array_filter($requetes, fn ($s) => str_starts_with($s, 'update')));
        expect($mises)->toHaveCount(1)->and($requetes[0])->toStartWith('update');
    });

    it('verrouillerSiLibre refuse un créneau passé', function () {
        $c = creneauVerrou(jours: -1);

        expect($c->verrouillerSiLibre(300, User::factory()->create()->id))->toBeFalse();
    });

    it('deux clients successifs sur le même créneau : un seul obtient le verrou, même avec une copie périmée', function () {
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $c = creneauVerrou();
        // Les deux clients ont lu le créneau « libre » avant que l'un ne le prenne : seule la condition en base tranche.
        $copieA = Creneau::find($c->id);
        $copieB = Creneau::find($c->id);

        $resultats = [$copieA->verrouillerSiLibre(300, $a->id), $copieB->verrouillerSiLibre(300, $b->id)];

        expect($resultats)->toBe([true, false])->and($c->fresh()->verrouille_par)->toBe($a->id);
    });
});
