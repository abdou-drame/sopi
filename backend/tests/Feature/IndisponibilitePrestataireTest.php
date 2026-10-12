<?php

use App\Enums\StatutCompte;
use App\Enums\StatutCreneau;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const URL_INDISPONIBILITES = '/api/v1/prestataire/agenda/indisponibilites';

function prestataireIndispo(): Prestataire
{
    return Prestataire::factory()->for(User::factory()->prestataire()->statut(StatutCompte::Actif), 'user')->create();
}

/** Créneau d'une heure le jour +$jours à $heure (HH:MM), dans l'état demandé. */
function creneauIndispo(Prestataire $p, int $jours, string $heure, string $etat = 'libre'): Creneau
{
    $debut = now()->addDays($jours)->setTimeFromTimeString($heure);

    return Creneau::factory()->{$etat}()->for($p)->create(['date_debut' => $debut, 'date_fin' => $debut->copy()->addMinutes(60)]);
}

function plageIndispo(array $surcharge = []): array
{
    return array_merge([
        'debut' => now()->addDays(2)->format('Y-m-d').' 08:00',
        'fin' => now()->addDays(2)->format('Y-m-d').' 18:00',
    ], $surcharge);
}

function statutDe(Creneau $c): StatutCreneau
{
    return $c->fresh()->statut;
}

it('renvoie 401 sans jeton, puis 403 pour un client et un administrateur', function () {
    $this->postJson(URL_INDISPONIBILITES, plageIndispo())->assertUnauthorized();

    foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
        $this->actingAs($user, 'sanctum');
        $this->postJson(URL_INDISPONIBILITES, plageIndispo())->assertForbidden();
    }
});

describe('blocage', function () {
    it('bloque les créneaux libres et les verrous de la plage, et laisse un créneau réservé sans réservation active', function () {
        $p = prestataireIndispo();
        $this->actingAs($p->user, 'sanctum');
        $a = creneauIndispo($p, 2, '09:00');
        $b = creneauIndispo($p, 2, '10:00');
        $expire = creneauIndispo($p, 2, '11:00', 'verrouille');
        $expire->forceFill(['verrou_expire_a' => now()->subMinute()])->save();
        $valide = creneauIndispo($p, 2, '12:00', 'verrouille');
        $reserve = creneauIndispo($p, 2, '13:00', 'reserve');

        $this->postJson(URL_INDISPONIBILITES, plageIndispo() + ['motif' => 'Congé'])->assertOk()->assertExactJson([
            'creneauxBloques' => 3,
            'creneauxReserves' => 0,
            'creneauxVerrouilles' => 1,
            'reservationsAnnulees' => 0,
        ]);

        expect(statutDe($a))->toBe(StatutCreneau::Indisponible)
            ->and(statutDe($b))->toBe(StatutCreneau::Indisponible)
            ->and(statutDe($expire))->toBe(StatutCreneau::Indisponible)
            ->and($expire->fresh()->verrou_expire_a)->toBeNull()
            ->and(statutDe($valide))->toBe(StatutCreneau::Indisponible)
            ->and($valide->fresh()->verrou_expire_a)->toBeNull()
            ->and($valide->fresh()->verrouille_par)->toBeNull()
            ->and(statutDe($reserve))->toBe(StatutCreneau::Reserve);
    });

    it('laisse intacts les créneaux hors de la plage, et bloque ceux qui sont à cheval sur un bord', function () {
        $p = prestataireIndispo();
        $this->actingAs($p->user, 'sanctum');
        $avant = creneauIndispo($p, 2, '06:00');
        $finitAuDebut = creneauIndispo($p, 2, '07:00');
        $chevaucheDebut = creneauIndispo($p, 2, '07:30');
        $chevaucheFin = creneauIndispo($p, 2, '17:30');
        $commenceALaFin = creneauIndispo($p, 2, '18:00');
        $autreJour = creneauIndispo($p, 3, '09:00');

        $this->postJson(URL_INDISPONIBILITES, plageIndispo())->assertOk()->assertJsonPath('creneauxBloques', 2);

        expect(statutDe($chevaucheDebut))->toBe(StatutCreneau::Indisponible)
            ->and(statutDe($chevaucheFin))->toBe(StatutCreneau::Indisponible)
            ->and(statutDe($avant))->toBe(StatutCreneau::Libre)
            ->and(statutDe($finitAuDebut))->toBe(StatutCreneau::Libre)
            ->and(statutDe($commenceALaFin))->toBe(StatutCreneau::Libre)
            ->and(statutDe($autreJour))->toBe(StatutCreneau::Libre);
    });

    it('n\'affecte pas les créneaux d\'un autre prestataire', function () {
        $autrePrestataire = prestataireIndispo();
        $autre = creneauIndispo($autrePrestataire, 2, '09:00');
        $reserveAutre = creneauIndispo($autrePrestataire, 2, '10:00', 'reserve');
        $p = prestataireIndispo();
        $this->actingAs($p->user, 'sanctum');

        $this->postJson(URL_INDISPONIBILITES, plageIndispo())->assertOk()->assertJsonPath('creneauxBloques', 0)->assertJsonPath('creneauxReserves', 0);

        expect(statutDe($autre))->toBe(StatutCreneau::Libre)->and(statutDe($reserveAutre))->toBe(StatutCreneau::Reserve);
    });

    it('est idempotent', function () {
        $p = prestataireIndispo();
        $this->actingAs($p->user, 'sanctum');
        creneauIndispo($p, 2, '09:00');
        creneauIndispo($p, 2, '10:00');

        $this->postJson(URL_INDISPONIBILITES, plageIndispo())->assertOk()->assertJsonPath('creneauxBloques', 2);
        $this->postJson(URL_INDISPONIBILITES, plageIndispo())->assertOk()->assertJsonPath('creneauxBloques', 0);
        expect(Creneau::where('statut', 'indisponible')->count())->toBe(2);
    });

    it('accepte un début passé tant que la fin est dans le futur', function () {
        $p = prestataireIndispo();
        $this->actingAs($p->user, 'sanctum');

        $this->postJson(URL_INDISPONIBILITES, [
            'debut' => now()->subHours(2)->format('Y-m-d H:i'),
            'fin' => now()->addHours(2)->format('Y-m-d H:i'),
        ])->assertOk();
    });

    it('bloque par une seule requête UPDATE', function () {
        $p = prestataireIndispo();
        $this->actingAs($p->user, 'sanctum');
        foreach (['09:00', '10:00', '11:00'] as $h) {
            creneauIndispo($p, 2, $h);
        }
        $requetes = [];
        DB::listen(function ($q) use (&$requetes) {
            if (str_starts_with(strtolower(ltrim($q->sql)), 'update')) {
                $requetes[] = $q->sql;
            }
        });

        $this->postJson(URL_INDISPONIBILITES, plageIndispo())->assertOk()->assertJsonPath('creneauxBloques', 3);

        expect($requetes)->toHaveCount(1)->and($requetes[0])->toContain('creneaux');
    });
});

describe('validation', function () {
    it('refuse une plage invalide', function (array $corps, string $champ) {
        $this->actingAs(prestataireIndispo()->user, 'sanctum');

        $this->postJson(URL_INDISPONIBILITES, $corps)->assertUnprocessable()->assertJsonValidationErrors([$champ]);
    })->with([
        'fin avant début' => [fn () => plageIndispo(['debut' => now()->addDays(3)->format('Y-m-d H:i'), 'fin' => now()->addDays(2)->format('Y-m-d H:i')]), 'fin'],
        'fin égale au début' => [fn () => plageIndispo(['debut' => now()->addDays(3)->format('Y-m-d H:i'), 'fin' => now()->addDays(3)->format('Y-m-d H:i')]), 'fin'],
        'fin dans le passé' => [fn () => plageIndispo(['debut' => now()->subDays(3)->format('Y-m-d H:i'), 'fin' => now()->subDay()->format('Y-m-d H:i')]), 'fin'],
        'plage trop longue' => [fn () => plageIndispo(['debut' => now()->addDay()->format('Y-m-d H:i'), 'fin' => now()->addDays(92)->format('Y-m-d H:i')]), 'fin'],
        'début absent' => [fn () => ['fin' => now()->addDay()->format('Y-m-d H:i')], 'debut'],
        'format invalide' => [fn () => plageIndispo(['debut' => 'demain']), 'debut'],
    ]);

    it('accepte une plage de 90 jours exactement', function () {
        $this->actingAs(prestataireIndispo()->user, 'sanctum');

        $this->postJson(URL_INDISPONIBILITES, plageIndispo(['debut' => now()->addDay()->format('Y-m-d H:i'), 'fin' => now()->addDays(91)->format('Y-m-d H:i')]))->assertOk();
    });
});
