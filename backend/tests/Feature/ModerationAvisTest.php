<?php

use App\Enums\StatutAvis;
use App\Enums\StatutCompte;
use App\Models\Avis;
use App\Models\Creneau;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => RateLimiter::clear('sanction'));

function prestataireModere(): Prestataire
{
    $prestataire = Prestataire::factory()->for(User::factory()->prestataire()->statut(StatutCompte::Actif), 'user')->create();
    Prestation::factory()->create(['prestataire_id' => $prestataire->id]);

    return $prestataire;
}

function avisModere(Prestataire $prestataire, int $note = 4, ?User $client = null): Avis
{
    $creneau = Creneau::factory()->for($prestataire)->reserve()->create();
    $reservation = Reservation::factory()->terminee()->create([
        'client_id' => ($client ?? User::factory()->create())->id,
        'creneau_id' => $creneau->id,
    ]);

    return Avis::factory()->note($note)->commente('Un commentaire.')->create(['reservation_id' => $reservation->id]);
}

function masquerDirect(Avis $avis, ?User $admin = null): Avis
{
    $admin ??= User::factory()->administrateur()->create();
    DB::table('avis')->where('id', $avis->id)->update(['masque_le' => now(), 'modere_le' => now(), 'modere_par_id' => $admin->id, 'motif_moderation' => 'Motif de test.']);

    return $avis->fresh();
}

function motifValide(): array
{
    return ['motif' => 'Propos injurieux sans rapport avec la prestation.'];
}

describe('signalement par le prestataire', function () {
    it('exige un jeton puis le rôle prestataire', function () {
        $avis = avisModere(prestataireModere());
        $url = '/api/v1/prestataire/avis/'.$avis->id.'/signaler';

        $this->postJson($url, motifValide())->assertUnauthorized();
        foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->postJson($url, motifValide())->assertForbidden();
        }
        expect($avis->fresh()->signale_le)->toBeNull();
    });

    it('signale un avis reçu, avec le motif, et le laisse affiché', function () {
        $p = prestataireModere();
        $avis = avisModere($p);

        $this->actingAs($p->user, 'sanctum')->postJson('/api/v1/prestataire/avis/'.$avis->id.'/signaler', ['motif' => '  Cet avis concerne une autre prestation.  '])
            ->assertOk()->assertJsonPath('signale', true)->assertJsonPath('id', $avis->id);

        $avis->refresh();
        expect($avis->signale_le)->not->toBeNull()
            ->and($avis->motif_signalement)->toBe('Cet avis concerne une autre prestation.')
            ->and($avis->masque_le)->toBeNull();
    });

    it('refuse un deuxième signalement (409)', function () {
        $p = prestataireModere();
        $avis = avisModere($p);
        $this->actingAs($p->user, 'sanctum');

        $this->postJson('/api/v1/prestataire/avis/'.$avis->id.'/signaler', motifValide())->assertOk();
        $this->postJson('/api/v1/prestataire/avis/'.$avis->id.'/signaler', ['motif' => 'Un autre motif suffisant.'])
            ->assertStatus(409)->assertJsonPath('message', 'Cet avis a déjà été signalé.');

        expect($avis->fresh()->motif_signalement)->toBe(motifValide()['motif']);
    });

    it('répond 404 pour l\'avis d\'un autre prestataire, un avis masqué ou un identifiant invalide', function () {
        $moi = prestataireModere();
        $autre = avisModere(prestataireModere());
        $masque = masquerDirect(avisModere($moi));
        $this->actingAs($moi->user, 'sanctum');

        foreach ([$autre->id, $masque->id, fake()->uuid(), 'pas-un-uuid'] as $id) {
            $this->postJson('/api/v1/prestataire/avis/'.$id.'/signaler', motifValide())
                ->assertNotFound()->assertJsonPath('message', 'Avis introuvable.');
        }
        expect($autre->fresh()->signale_le)->toBeNull();
    });

    it('refuse de signaler un avis déjà modéré (conservé)', function () {
        $p = prestataireModere();
        $avis = avisModere($p);
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')->postJson('/api/v1/admin/avis/'.$avis->id.'/conserver')->assertOk();

        $this->actingAs($p->user, 'sanctum')->postJson('/api/v1/prestataire/avis/'.$avis->id.'/signaler', motifValide())
            ->assertStatus(409)->assertJsonPath('message', 'Cet avis a déjà été modéré.');
    });

    it('valide le motif', function (array $corps, string $message) {
        $p = prestataireModere();
        $avis = avisModere($p);

        $this->actingAs($p->user, 'sanctum')->postJson('/api/v1/prestataire/avis/'.$avis->id.'/signaler', $corps)
            ->assertUnprocessable()->assertJsonPath('errors.motif.0', $message);
    })->with([
        'absent' => [[], 'Le motif du signalement est obligatoire.'],
        'trop court' => [['motif' => 'court'], 'Le motif doit contenir au moins 10 caractères.'],
        'trop long' => [['motif' => str_repeat('a', 501)], 'Le motif ne peut pas dépasser 500 caractères.'],
    ]);
});

describe('liste admin', function () {
    it('est réservée aux administrateurs', function () {
        $this->getJson('/api/v1/admin/avis')->assertUnauthorized();
        foreach ([User::factory()->create(), prestataireModere()->user] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/avis')->assertForbidden();
        }
    });

    it('liste tous les avis paginés par 20, du plus récent au plus ancien, avec signalement et modération', function () {
        $p = prestataireModere();
        $ancien = avisModere($p);
        DB::table('avis')->where('id', $ancien->id)->update(['created_at' => now()->subDays(3), 'signale_le' => now()->subDay(), 'motif_signalement' => 'Avis injuste.']);
        $recent = avisModere($p);
        foreach (range(1, 20) as $i) {
            avisModere(prestataireModere());
        }

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $page1 = $this->getJson('/api/v1/admin/avis')->assertOk()->assertJsonPath('meta.total', 22)->assertJsonPath('meta.per_page', 20);
        expect($page1->json('data'))->toHaveCount(20);

        $page2 = $this->getJson('/api/v1/admin/avis?page=2')->assertOk();
        expect(collect($page2->json('data'))->pluck('id')->last())->toBe($ancien->id)
            ->and($page2->json('data.1.signaleLe'))->not->toBeNull()
            ->and($page2->json('data.1.motifSignalement'))->toBe('Avis injuste.')
            ->and($page2->json('data.1.client.nom'))->not->toBeNull()
            ->and($page2->json('data.1.prestataire.nomEntreprise'))->toBe($p->nom_entreprise);
    });

    it('filtre par prestataire, note, masqué, signalé et à traiter', function () {
        $a = prestataireModere();
        $b = prestataireModere();
        $a1 = avisModere($a, 1);
        $a5 = avisModere($a, 5);
        $b3 = avisModere($b, 3);
        masquerDirect($a5);
        DB::table('avis')->where('id', $a1->id)->update(['signale_le' => now(), 'motif_signalement' => 'Avis injuste.']);
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');

        $ids = fn (string $requete) => collect($this->getJson('/api/v1/admin/avis?'.$requete)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
        $tri = fn (Avis ...$avis) => collect($avis)->pluck('id')->sort()->values()->all();

        expect($ids('prestataire='.$b->id))->toBe($tri($b3))
            ->and($ids('note=5'))->toBe($tri($a5))
            ->and($ids('masque=1'))->toBe($tri($a5))
            ->and($ids('masque=0&prestataire='.$a->id))->toBe($tri($a1))
            ->and($ids('signale=1'))->toBe($tri($a1))
            ->and($ids('aTraiter=1'))->toBe($tri($a1))
            ->and($ids('signale=0'))->toBe($tri($a5, $b3));
    });

    it('valide les filtres', function (string $requete, string $champ, string $message) {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')->getJson('/api/v1/admin/avis?'.$requete)
            ->assertUnprocessable()->assertJsonPath("errors.$champ.0", $message);
    })->with([
        'note 6' => ['note=6', 'note', 'La note doit être comprise entre 1 et 5.'],
        'prestataire invalide' => ['prestataire=abc', 'prestataire', 'Le prestataire doit être un identifiant valide.'],
        'masque invalide' => ['masque=peut-etre', 'masque', 'Le filtre « masque » doit être vrai ou faux.'],
    ]);

    it('n\'utilise pas un nombre de requêtes qui dépend du nombre d\'avis', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $compter = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/admin/avis')->assertOk();

            return count(DB::getQueryLog());
        };

        avisModere(prestataireModere());
        $pour1 = $compter();
        foreach (range(1, 6) as $i) {
            avisModere(prestataireModere());
        }

        expect($compter())->toBe($pour1);
    });
});

describe('masquer, conserver, rétablir', function () {
    it('refuse les non-administrateurs', function () {
        $p = prestataireModere();
        $avis = avisModere($p);

        foreach (['masquer', 'conserver', 'retablir'] as $action) {
            $this->postJson('/api/v1/admin/avis/'.$avis->id.'/'.$action, motifValide())->assertUnauthorized();
        }
        foreach (['masquer', 'conserver', 'retablir'] as $action) {
            foreach ([$p->user, User::factory()->create()] as $user) {
                $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/avis/'.$avis->id.'/'.$action, motifValide())->assertForbidden();
            }
        }
        expect($avis->fresh()->masque_le)->toBeNull()->and($avis->fresh()->modere_le)->toBeNull();
    });

    it('masque un avis avec la trace de l\'administrateur, de la date et du motif', function () {
        $avis = avisModere(prestataireModere());
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/avis/'.$avis->id.'/masquer', ['motif' => '  '.motifValide()['motif'].'  '])
            ->assertOk()->assertJsonPath('masque', true)->assertJsonPath('moderePar', $admin->id)->assertJsonPath('motifModeration', motifValide()['motif']);

        $avis->refresh();
        expect($avis->masque_le)->not->toBeNull()->and($avis->modere_le)->not->toBeNull()->and($avis->modere_par_id)->toBe($admin->id);
    });

    it('exige un motif pour masquer', function (array $corps, string $message) {
        $avis = avisModere(prestataireModere());

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')->postJson('/api/v1/admin/avis/'.$avis->id.'/masquer', $corps)
            ->assertUnprocessable()->assertJsonPath('errors.motif.0', $message);
        expect($avis->fresh()->masque_le)->toBeNull();
    })->with([
        'absent' => [[], 'Le motif de la modération est obligatoire.'],
        'trop court' => [['motif' => 'court'], 'Le motif doit contenir au moins 10 caractères.'],
    ]);

    it('refuse de masquer deux fois (409)', function () {
        $avis = avisModere(prestataireModere());
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');

        $this->postJson('/api/v1/admin/avis/'.$avis->id.'/masquer', motifValide())->assertOk();
        $this->postJson('/api/v1/admin/avis/'.$avis->id.'/masquer', ['motif' => 'Un second motif suffisant.'])
            ->assertStatus(409)->assertJsonPath('message', 'Cet avis est déjà masqué.');

        expect($avis->fresh()->motif_moderation)->toBe(motifValide()['motif']);
    });

    it('ne laisse passer qu\'une décision si l\'avis est masqué entre la lecture et l\'écriture', function () {
        $avis = avisModere(prestataireModere());
        $admin = User::factory()->administrateur()->create();
        $premier = User::factory()->administrateur()->create();

        // Une autre requête a masqué l'avis juste après notre lecture : la mise à jour conditionnelle ne touche aucune ligne.
        DB::table('avis')->where('id', $avis->id)->update(['masque_le' => now(), 'modere_le' => now(), 'modere_par_id' => $premier->id, 'motif_moderation' => 'Premier motif.']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/avis/'.$avis->id.'/masquer', motifValide())->assertStatus(409);
        expect($avis->fresh()->modere_par_id)->toBe($premier->id)->and($avis->fresh()->motif_moderation)->toBe('Premier motif.');
    });

    it('conserve un avis signalé : il reste affiché et n\'est plus à traiter', function () {
        $p = prestataireModere();
        $avis = avisModere($p);
        DB::table('avis')->where('id', $avis->id)->update(['signale_le' => now(), 'motif_signalement' => 'Avis injuste.']);
        $admin = User::factory()->administrateur()->create();
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/v1/admin/avis/'.$avis->id.'/conserver')->assertOk()->assertJsonPath('masque', false)->assertJsonPath('moderePar', $admin->id);
        $this->postJson('/api/v1/admin/avis/'.$avis->id.'/conserver')->assertStatus(409)->assertJsonPath('message', 'Cet avis a déjà été modéré.');

        expect($avis->fresh()->masque_le)->toBeNull();
        expect($this->getJson('/api/v1/admin/avis?aTraiter=1')->json('data'))->toBeEmpty();
    });

    it('rétablit un avis masqué en gardant la trace, et répond 409 s\'il n\'est pas masqué', function () {
        $avis = avisModere(prestataireModere());
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');

        $this->postJson('/api/v1/admin/avis/'.$avis->id.'/retablir')->assertStatus(409)->assertJsonPath('message', 'Cet avis n\'est pas masqué.');

        $this->postJson('/api/v1/admin/avis/'.$avis->id.'/masquer', motifValide())->assertOk();
        $this->postJson('/api/v1/admin/avis/'.$avis->id.'/retablir')->assertOk()->assertJsonPath('masque', false);
        $this->postJson('/api/v1/admin/avis/'.$avis->id.'/retablir')->assertStatus(409);

        $avis->refresh();
        expect($avis->masque_le)->toBeNull()->and($avis->modere_le)->not->toBeNull()->and($avis->motif_moderation)->toBe(motifValide()['motif']);
    });

    it('répond 404 pour un avis inconnu', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        foreach (['masquer', 'conserver', 'retablir'] as $action) {
            foreach ([fake()->uuid(), 'pas-un-uuid'] as $id) {
                $this->postJson('/api/v1/admin/avis/'.$id.'/'.$action, motifValide())->assertNotFound()->assertJsonPath('message', 'Avis introuvable.');
            }
        }
    });

    it('limite les modifications à 10 par minute', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $id = fake()->uuid();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/admin/avis/'.$id.'/retablir')->assertNotFound();
        }
        $this->postJson('/api/v1/admin/avis/'.$id.'/retablir')->assertStatus(429);
    });
});

describe('effet d\'un avis masqué', function () {
    it('est exclu de la note moyenne, du nombre d\'avis, de la liste publique, de celle du prestataire, du profil et de la recherche', function () {
        $p = prestataireModere();
        avisModere($p, 5);
        $mauvais = avisModere($p, 1);
        $client = User::factory()->create();
        $this->actingAs($client, 'sanctum');

        $this->getJson('/api/v1/prestataires/'.$p->id)->assertOk()->assertJsonPath('noteMoyenne', 3)->assertJsonPath('nombreAvis', 2);

        masquerDirect($mauvais);

        $this->getJson('/api/v1/prestataires/'.$p->id)->assertOk()->assertJsonPath('noteMoyenne', 5)->assertJsonPath('nombreAvis', 1);
        $liste = $this->getJson('/api/v1/prestataires/'.$p->id.'/avis')->assertOk()->assertJsonPath('noteMoyenne', 5)->assertJsonPath('nombreAvis', 1);
        expect(collect($liste->json('data'))->pluck('id'))->not->toContain($mauvais->id)->toHaveCount(1);

        $fiche = collect($this->getJson('/api/v1/prestataires')->assertOk()->json('data'))->firstWhere('id', $p->id);
        expect($fiche['noteMoyenne'])->toBe(5)->and($fiche['nombreAvis'])->toBe(1);

        $this->actingAs($p->user, 'sanctum');
        $miens = $this->getJson('/api/v1/prestataire/avis')->assertOk()->assertJsonPath('noteMoyenne', 5)->assertJsonPath('nombreAvis', 1);
        expect(collect($miens->json('data'))->pluck('id'))->not->toContain($mauvais->id);
    });

    it('un prestataire dont tous les avis sont masqués n\'a plus de note', function () {
        $p = prestataireModere();
        masquerDirect(avisModere($p, 2));
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->getJson('/api/v1/prestataires/'.$p->id)->assertOk()->assertJsonPath('noteMoyenne', null)->assertJsonPath('nombreAvis', 0);
    });

    it('revient dans les calculs une fois rétabli', function () {
        $p = prestataireModere();
        $avis = avisModere($p, 2);
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');
        $this->postJson('/api/v1/admin/avis/'.$avis->id.'/masquer', motifValide())->assertOk();
        $this->postJson('/api/v1/admin/avis/'.$avis->id.'/retablir')->assertOk();

        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson('/api/v1/prestataires/'.$p->id)->assertOk()->assertJsonPath('noteMoyenne', 2)->assertJsonPath('nombreAvis', 1);
    });

    it('reste visible de son auteur avec un indicateur neutre, sans motif', function () {
        $p = prestataireModere();
        $auteur = User::factory()->create();
        $avis = avisModere($p, 1, $auteur);
        masquerDirect($avis);

        $reponse = $this->actingAs($auteur, 'sanctum')->getJson('/api/v1/reservations')->assertOk();
        $reservation = collect($reponse->json('data'))->firstWhere('id', $avis->reservation_id);

        expect($reservation['avis']['note'])->toBe(1)
            ->and($reservation['avis']['affichePubliquement'])->toBeFalse()
            ->and($reservation['avis'])->not->toHaveKeys(['motifModeration', 'motif', 'masque']);
    });

    it('un avis affiché garde affichePubliquement à vrai chez son auteur', function () {
        $auteur = User::factory()->create();
        $avis = avisModere(prestataireModere(), 4, $auteur);

        $reponse = $this->actingAs($auteur, 'sanctum')->getJson('/api/v1/reservations')->assertOk();

        expect(collect($reponse->json('data'))->firstWhere('id', $avis->reservation_id)['avis']['affichePubliquement'])->toBeTrue();
    });

    it('ne change pas le nombre de requêtes de la recherche selon les avis masqués', function () {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $compter = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/prestataires')->assertOk();

            return count(DB::getQueryLog());
        };

        $p = prestataireModere();
        masquerDirect(avisModere($p));
        $pour1 = $compter();
        foreach (range(1, 4) as $i) {
            masquerDirect(avisModere(prestataireModere()));
        }

        expect($compter())->toBe($pour1);
    });
});

describe('statut calculé de l\'avis', function () {
    it('vaut PUBLIE par défaut, SIGNALE après signalement, SUPPRIME une fois masqué', function () {
        $avis = avisModere(prestataireModere());
        expect($avis->statut)->toBe(StatutAvis::Publie);

        DB::table('avis')->where('id', $avis->id)->update(['signale_le' => now(), 'motif_signalement' => 'Avis injuste.']);
        expect($avis->fresh()->statut)->toBe(StatutAvis::Signale);

        expect(masquerDirect($avis->fresh())->statut)->toBe(StatutAvis::Supprime);
    });

    it('repasse à PUBLIE après conservation ou rétablissement', function () {
        $p = prestataireModere();
        $signale = avisModere($p);
        DB::table('avis')->where('id', $signale->id)->update(['signale_le' => now(), 'motif_signalement' => 'Avis injuste.']);
        $masque = avisModere($p);
        DB::table('avis')->where('id', $masque->id)->update(['signale_le' => now(), 'motif_signalement' => 'Avis injuste.']);
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum');

        $this->postJson('/api/v1/admin/avis/'.$signale->id.'/conserver')->assertOk()->assertJsonPath('statut', 'publie');
        $this->postJson('/api/v1/admin/avis/'.$masque->id.'/masquer', motifValide())->assertOk()->assertJsonPath('statut', 'supprime');
        $this->postJson('/api/v1/admin/avis/'.$masque->id.'/retablir')->assertOk()->assertJsonPath('statut', 'publie');

        expect($signale->fresh()->statut)->toBe(StatutAvis::Publie)->and($masque->fresh()->statut)->toBe(StatutAvis::Publie);
    });

    it('expose le statut dans la liste de l\'administrateur seulement', function () {
        $p = prestataireModere();
        $avis = avisModere($p);
        DB::table('avis')->where('id', $avis->id)->update(['signale_le' => now(), 'motif_signalement' => 'Avis injuste.']);

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')->getJson('/api/v1/admin/avis')
            ->assertOk()->assertJsonPath('data.0.statut', 'signale');

        $this->actingAs($p->user, 'sanctum')->getJson('/api/v1/prestataire/avis')->assertOk()->assertJsonMissingPath('data.0.statut');
        $this->actingAs($avis->client, 'sanctum')->getJson('/api/v1/prestataires/'.$p->id.'/avis')->assertOk()->assertJsonMissingPath('data.0.statut');
    });
});
