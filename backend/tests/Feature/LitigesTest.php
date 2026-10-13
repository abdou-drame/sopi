<?php

use App\Enums\DecisionLitige;
use App\Enums\StatutLitige;
use App\Enums\StatutReservation;
use App\Events\LitigeCloture;
use App\Events\LitigeOuvert;
use App\Models\Litige;
use App\Models\LitigePreuve;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Image PNG minimale valide (1 pixel), pour les preuves. */
function pngPreuve(string $nom = 'preuve.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nom, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
}

/** Fichier nommé .png mais dont le contenu réel est du texte (le type est lu sur le contenu). */
function fauxPng(): UploadedFile
{
    $chemin = tempnam(sys_get_temp_dir(), 'faux');
    file_put_contents($chemin, '<?php echo 1;');

    return new UploadedFile($chemin, 'faux.png', 'image/png', null, true);
}

function reservationDe(User $client, StatutReservation $statut = StatutReservation::Confirmee): Reservation
{
    return Reservation::factory()->create(['client_id' => $client->id, 'statut' => $statut->value]);
}

function descriptionLitige(): string
{
    return 'Le prestataire ne s\'est pas présenté au rendez-vous.';
}

describe('ouverture par le client', function () {
    it('exige un jeton puis le rôle client', function () {
        $reservation = Reservation::factory()->confirmee()->create();
        $url = '/api/v1/reservations/'.$reservation->id.'/litiges';

        $this->postJson($url, ['description' => descriptionLitige()])->assertUnauthorized();
        foreach ([User::factory()->prestataire()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->postJson($url, ['description' => descriptionLitige()])->assertForbidden();
        }
        expect(Litige::count())->toBe(0);
    });

    it('ouvre un litige sur une réservation confirmée, en cours ou terminée, avec le prestataire de la réservation', function (StatutReservation $statut) {
        Event::fake([LitigeOuvert::class]);
        $client = User::factory()->create();
        $reservation = reservationDe($client, $statut);

        $reponse = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/reservations/'.$reservation->id.'/litiges', ['description' => '  '.descriptionLitige().'  '])
            ->assertCreated()
            ->assertJsonPath('statut', 'ouvert')
            ->assertJsonPath('description', descriptionLitige())
            ->assertJsonPath('reservation.id', $reservation->id)
            ->assertJsonPath('prestataire.nomEntreprise', $reservation->creneau->prestataire->nom_entreprise)
            ->assertJsonPath('decision', null);

        $litige = Litige::sole();
        expect($litige->client_id)->toBe($client->id)
            ->and($litige->prestataire_id)->toBe($reservation->creneau->prestataire_id)
            ->and($litige->statut)->toBe(StatutLitige::Ouvert)
            ->and($reponse->json('id'))->toBe($litige->id);
        Event::assertDispatched(LitigeOuvert::class, fn ($e) => $e->litige->is($litige));
    })->with([
        'confirmée' => StatutReservation::Confirmee,
        'en cours' => StatutReservation::EnCours,
        'terminée' => StatutReservation::Terminee,
    ]);

    it('refuse (409) une réservation d\'un autre statut', function (StatutReservation $statut) {
        $client = User::factory()->create();
        $reservation = reservationDe($client, $statut);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/reservations/'.$reservation->id.'/litiges', ['description' => descriptionLitige()])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Un litige ne peut être ouvert que sur une réservation confirmée, en cours ou terminée.']);
        expect(Litige::count())->toBe(0);
    })->with([
        StatutReservation::EnAttente, StatutReservation::Acceptee, StatutReservation::Refusee, StatutReservation::Annulee,
    ]);

    it('répond 404 sans fuite pour la réservation d\'un autre client ou un identifiant inconnu', function () {
        $client = User::factory()->create();
        $autre = reservationDe(User::factory()->create());

        foreach (['/api/v1/reservations/'.$autre->id.'/litiges', '/api/v1/reservations/'.fake()->uuid().'/litiges', '/api/v1/reservations/pas-un-uuid/litiges'] as $url) {
            $this->actingAs($client, 'sanctum')->postJson($url, ['description' => descriptionLitige()])
                ->assertNotFound()->assertExactJson(['message' => 'Réservation introuvable.']);
        }
        expect(Litige::count())->toBe(0);
    });

    it('refuse (409) un second litige tant que le premier est ouvert, mais en accepte un nouveau après clôture', function () {
        $client = User::factory()->create();
        $reservation = reservationDe($client);
        $url = '/api/v1/reservations/'.$reservation->id.'/litiges';

        $this->actingAs($client, 'sanctum')->postJson($url, ['description' => descriptionLitige()])->assertCreated();
        $this->actingAs($client, 'sanctum')->postJson($url, ['description' => descriptionLitige()])
            ->assertStatus(409)->assertExactJson(['message' => 'Un litige est déjà ouvert sur cette réservation.']);
        expect(Litige::count())->toBe(1);

        Litige::query()->update(['statut' => 'cloture', 'decision' => 'rejet', 'motif_decision' => 'Non fondé.', 'decide_par_id' => User::factory()->administrateur()->create()->id, 'cloture_le' => now()]);

        $this->actingAs($client, 'sanctum')->postJson($url, ['description' => descriptionLitige()])->assertCreated();
        expect(Litige::count())->toBe(2);
    });

    it('valide la description : obligatoire, 10 à 1000 caractères', function (mixed $description, string $message) {
        $client = User::factory()->create();
        $reservation = reservationDe($client);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/reservations/'.$reservation->id.'/litiges', $description === null ? [] : ['description' => $description])
            ->assertStatus(422)->assertJsonPath('errors.description.0', $message);
        expect(Litige::count())->toBe(0);
    })->with([
        'absente' => [null, 'La description du litige est obligatoire.'],
        'trop courte' => ['court', 'La description doit contenir au moins 10 caractères.'],
        'trop longue' => [str_repeat('a', 1001), 'La description ne peut pas dépasser 1000 caractères.'],
    ]);

    it('accepte une description de 10 et de 1000 caractères', function (int $longueur) {
        $client = User::factory()->create();
        $reservation = reservationDe($client);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/reservations/'.$reservation->id.'/litiges', ['description' => str_repeat('é', $longueur)])
            ->assertCreated();
    })->with([10, 1000]);

    it('limite l\'ouverture à 5 requêtes par minute et par utilisateur', function () {
        $client = User::factory()->create();
        $reservation = reservationDe($client);
        RateLimiter::clear($client->id);

        foreach (range(1, 5) as $i) {
            $this->actingAs($client, 'sanctum')->postJson('/api/v1/reservations/'.$reservation->id.'/litiges', ['description' => 'x'])->assertStatus(422);
        }
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/reservations/'.$reservation->id.'/litiges', ['description' => 'x'])
            ->assertStatus(429)->assertExactJson(['message' => 'Trop de tentatives. Réessayez dans quelques instants.']);
    });

    it('garantit l\'unicité du litige ouvert au niveau de la base (ouvertures simultanées)', function () {
        $reservation = Reservation::factory()->confirmee()->create();
        Litige::factory()->create(['reservation_id' => $reservation->id]);

        expect(fn () => Litige::factory()->create(['reservation_id' => $reservation->id]))->toThrow(UniqueConstraintViolationException::class);
        expect(Litige::count())->toBe(1);
    });
});

describe('preuves', function () {
    it('enregistre jusqu\'à 3 photos sur le disque privé sous un nom aléatoire et les rend à leur propriétaire seulement', function () {
        Storage::fake('local');
        $client = User::factory()->create();
        $reservation = reservationDe($client);

        $reponse = $this->actingAs($client, 'sanctum')->post('/api/v1/reservations/'.$reservation->id.'/litiges', [
            'description' => descriptionLitige(),
            'preuves' => [pngPreuve('a.png'), pngPreuve('b.png'), pngPreuve('c.png')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('nombrePreuves', 3);

        $litige = Litige::sole();
        $preuves = LitigePreuve::query()->where('litige_id', $litige->id)->get();
        expect($preuves)->toHaveCount(3);
        foreach ($preuves as $preuve) {
            expect($preuve->fichier)->toMatch('/^[0-9a-f-]{36}\.png$/');
            Storage::disk('local')->assertExists('litiges/'.$litige->id.'/'.$preuve->fichier);
        }

        $premiere = $reponse->json('preuves.0');
        $this->actingAs($client, 'sanctum')->get($premiere['url'])
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson($premiere['url'])->assertNotFound();
        $this->actingAs($litige->prestataire->user, 'sanctum')
            ->get('/api/v1/prestataire/litiges/'.$litige->id.'/preuves/'.$preuves->first()->id)->assertOk();
    });

    it('refuse plus de 3 preuves et les fichiers qui ne sont pas des images', function () {
        Storage::fake('local');
        $client = User::factory()->create();
        $reservation = reservationDe($client);
        $url = '/api/v1/reservations/'.$reservation->id.'/litiges';

        $this->actingAs($client, 'sanctum')->post($url, [
            'description' => descriptionLitige(),
            'preuves' => [pngPreuve(), pngPreuve(), pngPreuve(), pngPreuve()],
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('errors.preuves.0', 'Vous pouvez joindre 3 photos au plus.');

        $faux = $this->actingAs($client, 'sanctum')->post($url, [
            'description' => descriptionLitige(),
            'preuves' => [fauxPng()],
        ], ['Accept' => 'application/json'])->assertStatus(422);
        expect($faux->json('errors')['preuves.0'][0])->toBe('Chaque preuve doit être une image JPG, PNG ou WebP.');

        expect(Litige::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('répond 404 pour une preuve inconnue', function () {
        $litige = Litige::factory()->create();

        $this->actingAs(User::find($litige->client_id), 'sanctum')
            ->getJson('/api/v1/litiges/'.$litige->id.'/preuves/'.fake()->uuid())->assertNotFound()->assertExactJson(['message' => 'Preuve introuvable.']);
    });
});

describe('consultation par le client', function () {
    it('liste seulement ses litiges, du plus récent au plus ancien, paginés par 20', function () {
        $client = User::factory()->create();
        $ancien = Litige::factory()->create(['reservation_id' => reservationDe($client)->id, 'created_at' => now()->subDay()]);
        $recent = Litige::factory()->create(['reservation_id' => reservationDe($client)->id]);
        Litige::factory()->create();

        $reponse = $this->actingAs($client, 'sanctum')->getJson('/api/v1/litiges')->assertOk();

        expect(array_column($reponse->json('data'), 'id'))->toBe([$recent->id, $ancien->id])
            ->and($reponse->json('meta.per_page'))->toBe(20);
    });

    it('affiche le détail de son litige et répond 404 pour celui d\'un autre', function () {
        $litige = Litige::factory()->repondu()->create();
        $client = User::find($litige->client_id);

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/litiges/'.$litige->id)
            ->assertOk()->assertJsonPath('id', $litige->id)->assertJsonPath('reponsePrestataire', 'J\'étais sur place à l\'heure convenue.')
            ->assertJsonMissingPath('client')->assertJsonMissingPath('prestataire.telephone');

        foreach ([$litige->id, fake()->uuid(), 'nimporte'] as $id) {
            $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/litiges/'.$id)
                ->assertNotFound()->assertExactJson(['message' => 'Litige introuvable.']);
        }
    });

    it('ne montre rien aux prestataires et administrateurs sur les routes client', function () {
        foreach ([User::factory()->prestataire()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/litiges')->assertForbidden();
        }
    });

    it('exige un jeton', function () {
        $this->getJson('/api/v1/litiges')->assertUnauthorized();
    });
});

describe('espace prestataire', function () {
    it('liste et affiche les litiges qui le visent, le client réduit à son prénom et l\'initiale de son nom', function () {
        $litige = Litige::factory()->create();
        Litige::factory()->create();
        $prestataire = $litige->prestataire->user;
        $client = User::find($litige->client_id);

        $liste = $this->actingAs($prestataire, 'sanctum')->getJson('/api/v1/prestataire/litiges')->assertOk();
        expect($liste->json('data'))->toHaveCount(1)->and($liste->json('data.0.id'))->toBe($litige->id);

        $detail = $this->actingAs($prestataire, 'sanctum')->getJson('/api/v1/prestataire/litiges/'.$litige->id)
            ->assertOk()
            ->assertJsonPath('client.prenom', $client->prenom)
            ->assertJsonPath('client.initialeNom', mb_strtoupper(mb_substr($client->nom, 0, 1)));

        $texte = $detail->getContent();
        expect($texte)->not->toContain($client->telephone)->not->toContain($client->id);
        if ($client->email !== null) {
            expect($texte)->not->toContain($client->email);
        }
    });

    it('répond 404 pour le litige d\'un autre prestataire', function () {
        $litige = Litige::factory()->create();
        $autre = Litige::factory()->create()->prestataire->user;

        $this->actingAs($autre, 'sanctum')->getJson('/api/v1/prestataire/litiges/'.$litige->id)
            ->assertNotFound()->assertExactJson(['message' => 'Litige introuvable.']);
        $this->actingAs($autre, 'sanctum')->postJson('/api/v1/prestataire/litiges/'.$litige->id.'/reponse', ['reponse' => 'Ma version des faits.'])
            ->assertNotFound();
        expect($litige->fresh()->reponse_prestataire)->toBeNull();
    });

    it('enregistre une seule réponse tant que le litige est ouvert', function () {
        $litige = Litige::factory()->create();
        $prestataire = $litige->prestataire->user;

        $this->actingAs($prestataire, 'sanctum')->postJson('/api/v1/prestataire/litiges/'.$litige->id.'/reponse', ['reponse' => '  J\'étais sur place à l\'heure.  '])
            ->assertOk()->assertJsonPath('reponsePrestataire', 'J\'étais sur place à l\'heure.');
        expect($litige->fresh()->reponse_le)->not->toBeNull();

        $this->actingAs($prestataire, 'sanctum')->postJson('/api/v1/prestataire/litiges/'.$litige->id.'/reponse', ['reponse' => 'Une seconde version.'])
            ->assertStatus(409)->assertExactJson(['message' => 'Vous avez déjà répondu à ce litige.']);
        expect($litige->fresh()->reponse_prestataire)->toBe('J\'étais sur place à l\'heure.');
    });

    it('refuse (409) de répondre à un litige clôturé', function () {
        $litige = Litige::factory()->cloture()->create();

        $this->actingAs($litige->prestataire->user, 'sanctum')->postJson('/api/v1/prestataire/litiges/'.$litige->id.'/reponse', ['reponse' => 'Trop tard pour répondre.'])
            ->assertStatus(409)->assertExactJson(['message' => 'Ce litige est déjà clôturé.']);
        expect($litige->fresh()->reponse_prestataire)->toBeNull();
    });

    it('valide la réponse', function () {
        $litige = Litige::factory()->create();

        $this->actingAs($litige->prestataire->user, 'sanctum')->postJson('/api/v1/prestataire/litiges/'.$litige->id.'/reponse', [])
            ->assertStatus(422)->assertJsonPath('errors.reponse.0', 'Votre réponse est obligatoire.');
        $this->actingAs($litige->prestataire->user, 'sanctum')->postJson('/api/v1/prestataire/litiges/'.$litige->id.'/reponse', ['reponse' => 'court'])
            ->assertStatus(422)->assertJsonPath('errors.reponse.0', 'La réponse doit contenir au moins 10 caractères.');
    });

    it('refuse les clients et administrateurs sur ses routes', function () {
        foreach ([User::factory()->create(), User::factory()->administrateur()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/prestataire/litiges')->assertForbidden();
        }
    });
});

describe('administration', function () {
    it('refuse les non-administrateurs sur toutes les routes admin', function () {
        $litige = Litige::factory()->create();

        $this->getJson('/api/v1/admin/litiges')->assertUnauthorized();
        foreach ([User::find($litige->client_id), $litige->prestataire->user] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/litiges')->assertForbidden();
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/litiges/'.$litige->id)->assertForbidden();
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', ['decision' => 'rejet', 'motif' => 'Motif suffisant.'])->assertForbidden();
        }
        expect($litige->fresh()->statut)->toBe(StatutLitige::Ouvert);
    });

    it('liste les litiges ouverts par défaut (les plus anciens d\'abord), filtre par statut et pagine', function () {
        $admin = User::factory()->administrateur()->create();
        $recent = Litige::factory()->create();
        $ancien = Litige::factory()->create(['created_at' => now()->subDays(2)]);
        $clos = Litige::factory()->cloture()->create();

        $defaut = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/litiges')->assertOk();
        expect(array_column($defaut->json('data'), 'id'))->toBe([$ancien->id, $recent->id]);

        $clotures = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/litiges?statut=cloture')->json('data');
        expect(array_column($clotures, 'id'))->toBe([$clos->id]);

        expect($this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/litiges?statut=tous')->json('meta.total'))->toBe(3);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/litiges?statut=bidon')
            ->assertStatus(422)->assertJsonPath('errors.statut.0', 'Le statut doit être ouvert, cloture ou tous.');
    });

    it('pagine par 20', function () {
        $admin = User::factory()->administrateur()->create();
        Litige::factory()->count(21)->create();

        $page1 = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/litiges')->assertOk();
        expect($page1->json('data'))->toHaveCount(20)->and($page1->json('meta.last_page'))->toBe(2);
    });

    it('affiche le détail : réservation, paiements, parties avec coordonnées et preuves', function () {
        $admin = User::factory()->administrateur()->create();
        $litige = Litige::factory()->repondu()->create();
        $client = User::find($litige->client_id);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/litiges/'.$litige->id)
            ->assertOk()
            ->assertJsonPath('id', $litige->id)
            ->assertJsonPath('reservation.id', $litige->reservation_id)
            ->assertJsonPath('client.telephone', $client->telephone)
            ->assertJsonPath('prestataire.nomEntreprise', $litige->prestataire->nom_entreprise)
            ->assertJsonStructure(['paiements', 'preuves', 'prixApplique', 'reponsePrestataire']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/litiges/'.fake()->uuid())
            ->assertNotFound()->assertExactJson(['message' => 'Litige introuvable.']);
    });

    it('tranche par un rejet motivé et publie LitigeCloture', function () {
        Event::fake([LitigeCloture::class]);
        $admin = User::factory()->administrateur()->create();
        $litige = Litige::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', ['decision' => 'rejet', 'motif' => '  Signalement non fondé.  '])
            ->assertOk()->assertJsonPath('statut', 'cloture')->assertJsonPath('decision', 'rejet')->assertJsonPath('motifDecision', 'Signalement non fondé.');

        $litige->refresh();
        expect($litige->statut)->toBe(StatutLitige::Cloture)
            ->and($litige->decision)->toBe(DecisionLitige::Rejet)
            ->and($litige->decide_par_id)->toBe($admin->id)
            ->and($litige->cloture_le)->not->toBeNull();
        Event::assertDispatched(LitigeCloture::class, fn ($e) => $e->litige->is($litige));
    });

    it('ne touche à aucun argent ni statut de réservation en tranchant', function () {
        $admin = User::factory()->administrateur()->create();
        $litige = Litige::factory()->create();
        $statutReservation = $litige->reservation->statut;

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', ['decision' => 'rejet', 'motif' => 'Signalement non fondé.'])->assertOk();

        expect($litige->reservation->fresh()->statut)->toBe($statutReservation);
    });

    it('refuse (409) de trancher deux fois et garde la première décision', function () {
        Event::fake([LitigeCloture::class]);
        $admin = User::factory()->administrateur()->create();
        $litige = Litige::factory()->create();
        $url = '/api/v1/admin/litiges/'.$litige->id.'/trancher';

        $this->actingAs($admin, 'sanctum')->postJson($url, ['decision' => 'rejet', 'motif' => 'Première décision.'])->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson($url, ['decision' => 'rejet', 'motif' => 'Seconde décision.'])
            ->assertStatus(409)->assertExactJson(['message' => 'Ce litige est déjà clôturé.']);

        expect($litige->fresh()->motif_decision)->toBe('Première décision.');
        Event::assertDispatchedTimes(LitigeCloture::class, 1);
    });

    it('ne laisse passer qu\'une décision si le litige est clôturé entre la lecture et l\'écriture (concurrence simulée)', function () {
        Event::fake([LitigeCloture::class]);
        $litige = Litige::factory()->create();
        $concurrent = User::factory()->administrateur()->create();
        $admin = User::factory()->administrateur()->create();

        // L'autre administrateur clôture juste après la lecture du litige par le service.
        Litige::retrieved(function (Litige $l) use ($concurrent) {
            Litige::flushEventListeners();
            Litige::query()->whereKey($l->id)->update(['statut' => 'cloture', 'decision' => 'rejet', 'motif_decision' => 'Concurrent.', 'decide_par_id' => $concurrent->id, 'cloture_le' => now()]);
        });

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', ['decision' => 'rejet', 'motif' => 'Ma décision tardive.'])
            ->assertStatus(409)->assertExactJson(['message' => 'Ce litige est déjà clôturé.']);

        expect($litige->fresh()->decide_par_id)->toBe($concurrent->id);
        Event::assertNotDispatched(LitigeCloture::class);
        Litige::flushEventListeners();
    });

    it('valide la décision et le motif', function (array $corps, string $champ, string $message) {
        $litige = Litige::factory()->create();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')->postJson('/api/v1/admin/litiges/'.$litige->id.'/trancher', $corps)
            ->assertStatus(422)->assertJsonPath("errors.$champ.0", $message);
        expect($litige->fresh()->statut)->toBe(StatutLitige::Ouvert);
    })->with([
        'décision absente' => [['motif' => 'Un motif suffisant.'], 'decision', 'La décision est obligatoire.'],
        'décision inconnue' => [['decision' => 'condamnation', 'motif' => 'Un motif suffisant.'], 'decision', 'La décision doit être : rejet, avertissement, suspension_temporaire, bannissement_definitif.'],
        'motif absent' => [['decision' => 'rejet'], 'motif', 'Le motif de la décision est obligatoire.'],
        'motif trop court' => [['decision' => 'rejet', 'motif' => 'court'], 'motif', 'Le motif doit contenir au moins 10 caractères.'],
    ]);

    it('répond 404 pour trancher un litige inconnu', function () {
        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson('/api/v1/admin/litiges/'.fake()->uuid().'/trancher', ['decision' => 'rejet', 'motif' => 'Un motif suffisant.'])
            ->assertNotFound()->assertExactJson(['message' => 'Litige introuvable.']);
    });
});
