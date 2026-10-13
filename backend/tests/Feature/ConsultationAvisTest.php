<?php

use App\Enums\StatutCompte;
use App\Models\Avis;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** Prestataire (compte actif par défaut) avec une prestation active, donc visible dans la recherche. */
function prestataireNote(StatutCompte $statut = StatutCompte::Actif): Prestataire
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire()->statut($statut), 'user')->create();
    Prestation::factory()->create(['prestataire_id' => $prestataire->id]);

    return $prestataire;
}

/**
 * Avis de $note sur une nouvelle réservation terminée de ce prestataire. $client, $commentaire et $date sont facultatifs.
 */
function avisSur(Prestataire $prestataire, int $note, ?User $client = null, ?string $commentaire = null, mixed $date = null): Avis
{
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create();
    $reservation = Reservation::factory()->terminee()->create([
        'client_id' => ($client ?? User::factory()->create())->id,
        'creneau_id' => $creneau->id,
    ]);
    $avis = Avis::factory()->note($note)->commente($commentaire)->create(['reservation_id' => $reservation->id]);
    if ($commentaire === null) {
        $avis->commentaire = null;
        $avis->save();
    }
    if ($date !== null) {
        DB::table('avis')->where('id', $avis->id)->update(['created_at' => $date]);
    }

    return $avis->fresh();
}

function urlAvisDe(Prestataire|string $prestataire): string
{
    return '/api/v1/prestataires/'.($prestataire instanceof Prestataire ? $prestataire->id : $prestataire).'/avis';
}

describe('accès', function () {
    it('renvoie 401 sans jeton, 403 pour un prestataire et un administrateur', function () {
        $prestataire = prestataireNote();

        $this->getJson(urlAvisDe($prestataire))->assertUnauthorized();
        foreach ([User::factory()->prestataire()->create(), User::factory()->administrateur()->create()] as $user) {
            Sanctum::actingAs($user);
            $this->getJson(urlAvisDe($prestataire))->assertForbidden();
        }
    });

    it('réserve /prestataire/avis aux prestataires', function () {
        $this->getJson('/api/v1/prestataire/avis')->assertUnauthorized();
        foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/prestataire/avis')->assertForbidden();
        }
    });
});

describe('avis d\'un prestataire', function () {
    it('liste les avis du plus récent au plus ancien avec la synthèse', function () {
        $prestataire = prestataireNote();
        $ancien = avisSur($prestataire, 2, null, 'Moyen', now()->subDays(3));
        $recent = avisSur($prestataire, 5, null, 'Parfait', now()->subDay());
        avisSur(prestataireNote(), 1);
        Sanctum::actingAs(User::factory()->create());

        $reponse = $this->getJson(urlAvisDe($prestataire))->assertOk();

        expect($reponse->json('data.*.id'))->toBe([$recent->id, $ancien->id])
            ->and($reponse->json('data.0'))->toHaveKeys(['id', 'note', 'commentaire', 'auteur', 'dateCreation'])
            ->and($reponse->json('data.0.note'))->toBe(5)
            ->and($reponse->json('data.0.commentaire'))->toBe('Parfait')
            ->and($reponse->json('noteMoyenne'))->toBe(3.5)
            ->and($reponse->json('nombreAvis'))->toBe(2)
            ->and($reponse->json('meta.total'))->toBe(2);
    });

    it('arrondit la note moyenne à une décimale', function (array $notes, float $attendu) {
        $prestataire = prestataireNote();
        foreach ($notes as $note) {
            avisSur($prestataire, $note);
        }
        Sanctum::actingAs(User::factory()->create());

        $reponse = $this->getJson(urlAvisDe($prestataire))->assertOk();

        // JSON écrit 4.0 sous la forme 4 : comparaison numérique, pas de type.
        expect($reponse->json('noteMoyenne'))->toEqual($attendu)->and($reponse->json('nombreAvis'))->toBe(count($notes));
    })->with([
        'entière' => [[4, 4], 4.0],
        'un tiers vers le bas' => [[5, 4, 4], 4.3],
        'deux tiers vers le haut' => [[5, 5, 4], 4.7],
        'huitième vers le bas' => [[5, 4, 4, 4, 4, 4, 4, 4], 4.1],
        'quart exact vers le haut' => [[5, 4, 4, 4], 4.3],
        'minimum' => [[1], 1.0],
    ]);

    it('renvoie une note moyenne nulle et zéro avis pour un prestataire sans avis', function () {
        $prestataire = prestataireNote();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(urlAvisDe($prestataire))->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('noteMoyenne', null)
            ->assertJsonPath('nombreAvis', 0)
            ->assertJsonPath('meta.total', 0);
    });

    it('pagine par 20 et calcule la synthèse sur tous les avis, pas sur la page', function () {
        $prestataire = prestataireNote();
        foreach (range(1, 25) as $i) {
            avisSur($prestataire, $i <= 20 ? 5 : 1, null, null, now()->subMinutes(100 - $i));
        }
        Sanctum::actingAs(User::factory()->create());

        $page1 = $this->getJson(urlAvisDe($prestataire))->assertOk();
        $page2 = $this->getJson(urlAvisDe($prestataire).'?page=2')->assertOk();

        expect($page1->json('data'))->toHaveCount(20)
            ->and($page2->json('data'))->toHaveCount(5)
            ->and($page1->json('meta.per_page'))->toBe(20)
            ->and($page1->json('meta.last_page'))->toBe(2)
            ->and($page1->json('nombreAvis'))->toBe(25)
            ->and($page2->json('nombreAvis'))->toBe(25)
            ->and($page2->json('noteMoyenne'))->toBe(4.2)
            ->and(array_merge($page1->json('data.*.id'), $page2->json('data.*.id')))->toHaveCount(25)->each->toBeUuid();
    });

    it('n\'expose l\'auteur que par son prénom et l\'initiale de son nom, sans donnée personnelle', function () {
        $prestataire = prestataireNote();
        $auteur = User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo', 'telephone' => '+221771234567', 'email' => 'awa.diallo@example.test']);
        avisSur($prestataire, 4, $auteur, 'Très bien');
        Sanctum::actingAs(User::factory()->create());

        $reponse = $this->getJson(urlAvisDe($prestataire))->assertOk();
        $contenu = $reponse->getContent();

        expect($reponse->json('data.0.auteur'))->toBe('Awa D.')
            ->and($contenu)->not->toContain('Diallo')
            ->and($contenu)->not->toContain('771234567')
            ->and($contenu)->not->toContain('awa.diallo')
            ->and($contenu)->not->toContain($auteur->id)
            ->and($contenu)->not->toContain('client_id')
            ->and($contenu)->not->toContain('reservation')
            ->and(array_keys($reponse->json('data.0')))->toBe(['id', 'note', 'commentaire', 'auteur', 'dateCreation']);
    });

    it('gère un prénom ou un nom accentué ou absent', function () {
        $prestataire = prestataireNote();
        avisSur($prestataire, 4, User::factory()->create(['prenom' => 'Élodie', 'nom' => 'épée']), null, now()->subMinutes(2));
        avisSur($prestataire, 4, User::factory()->create(['prenom' => 'Moussa', 'nom' => '']), null, now()->subMinute());
        Sanctum::actingAs(User::factory()->create());

        $auteurs = $this->getJson(urlAvisDe($prestataire))->assertOk()->json('data.*.auteur');

        expect($auteurs)->toBe(['Moussa', 'Élodie É.']);
    });

    it('répond 404 pour un prestataire inconnu, un identifiant invalide ou un compte non actif', function (?StatutCompte $statut) {
        Sanctum::actingAs(User::factory()->create());
        $id = $statut ? prestataireNote($statut)->id : (string) Str::uuid();
        if ($statut) {
            avisSur(Prestataire::query()->findOrFail($id), 5);
        }

        $this->getJson(urlAvisDe($id))->assertNotFound()->assertExactJson(['message' => 'Prestataire introuvable.']);
    })->with([null, StatutCompte::EnAttenteValidation, StatutCompte::Refuse, StatutCompte::Suspendu, StatutCompte::Banni]);

    it('répond 404 pour un identifiant qui n\'est pas un UUID', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(urlAvisDe('pas-un-uuid'))->assertNotFound()->assertJsonPath('message', 'Prestataire introuvable.');
    });

    it('n\'utilise pas un nombre de requêtes qui dépend du nombre d\'avis', function () {
        $prestataire = prestataireNote();
        Sanctum::actingAs(User::factory()->create());
        $compter = function () use ($prestataire) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(urlAvisDe($prestataire))->assertOk();

            return count(DB::getQueryLog());
        };

        avisSur($prestataire, 5);
        $pour1 = $compter();
        foreach (range(1, 8) as $i) {
            avisSur($prestataire, 3);
        }

        expect($compter())->toBe($pour1);
    });
});

describe('mes avis (prestataire)', function () {
    it('liste uniquement les avis du prestataire connecté, avec la même forme', function () {
        $moi = prestataireNote();
        $autre = prestataireNote();
        $mien = avisSur($moi, 4, User::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']), 'Bien');
        avisSur($autre, 1);
        Sanctum::actingAs($moi->user);

        $reponse = $this->getJson('/api/v1/prestataire/avis')->assertOk();

        expect($reponse->json('data.*.id'))->toBe([$mien->id])
            ->and($reponse->json('data.0.auteur'))->toBe('Awa D.')
            ->and($reponse->json('noteMoyenne'))->toEqual(4)
            ->and($reponse->json('nombreAvis'))->toBe(1)
            ->and($reponse->getContent())->not->toContain('Diallo');
    });

    it('renvoie une liste vide et une note nulle sans avis', function () {
        Sanctum::actingAs(prestataireNote()->user);

        $this->getJson('/api/v1/prestataire/avis')->assertOk()->assertJsonPath('data', [])->assertJsonPath('noteMoyenne', null)->assertJsonPath('nombreAvis', 0);
    });
});

describe('note moyenne dans les prestataires', function () {
    it('affiche noteMoyenne et nombreAvis dans la recherche et dans le profil', function () {
        $avecAvis = prestataireNote();
        $sansAvis = prestataireNote();
        avisSur($avecAvis, 5);
        avisSur($avecAvis, 4);
        avisSur($avecAvis, 4);
        Sanctum::actingAs(User::factory()->create());

        $liste = collect($this->getJson('/api/v1/prestataires')->assertOk()->json('data'))->keyBy('id');
        $profil = $this->getJson('/api/v1/prestataires/'.$avecAvis->id)->assertOk();
        $profilSans = $this->getJson('/api/v1/prestataires/'.$sansAvis->id)->assertOk();

        expect($liste[$avecAvis->id]['noteMoyenne'])->toBe(4.3)
            ->and($liste[$avecAvis->id]['nombreAvis'])->toBe(3)
            ->and($liste[$sansAvis->id]['noteMoyenne'])->toBeNull()
            ->and($liste[$sansAvis->id]['nombreAvis'])->toBe(0)
            ->and($profil->json('noteMoyenne'))->toBe(4.3)
            ->and($profil->json('nombreAvis'))->toBe(3)
            ->and($profilSans->json('noteMoyenne'))->toBeNull()
            ->and($profilSans->json('nombreAvis'))->toBe(0);
    });

    it('ne multiplie pas les requêtes de la recherche avec le nombre de prestataires ni d\'avis', function () {
        Sanctum::actingAs(User::factory()->create());
        $compter = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/prestataires')->assertOk();

            return count(DB::getQueryLog());
        };

        avisSur(prestataireNote(), 5);
        $pour1 = $compter();
        foreach (range(1, 6) as $i) {
            $p = prestataireNote();
            avisSur($p, 4);
            avisSur($p, 2);
        }

        expect($compter())->toBe($pour1);
    });

    it('ne compte pas les avis des autres prestataires ni ne double les lignes avec plusieurs prestations', function () {
        $p = prestataireNote();
        Prestation::factory()->count(3)->create(['prestataire_id' => $p->id]);
        avisSur($p, 5);
        avisSur(prestataireNote(), 1);
        Sanctum::actingAs(User::factory()->create());

        $liste = collect($this->getJson('/api/v1/prestataires')->assertOk()->json('data'))->keyBy('id');

        expect($liste[$p->id]['nombreAvis'])->toBe(1)
            ->and($liste[$p->id]['noteMoyenne'])->toEqual(5)
            ->and($liste[$p->id]['nombrePrestations'])->toBe($p->prestations()->where('actif', true)->count());
    });
});
