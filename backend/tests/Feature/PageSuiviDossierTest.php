<?php

use App\Enums\StatutCompte;
use App\Enums\StatutJustificatif;
use App\Models\Justificatif;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Dossier avec un jeton connu (seul le hachage va en base) : renvoie [prestataire, jeton]. */
function dossierPourPage(StatutCompte $statut, array $prestataire = [], array $user = []): array
{
    $jeton = Str::random(48);
    $dossier = Prestataire::factory()
        ->for(User::factory()->prestataire()->statut($statut)->create(array_merge(['telephone' => '+221771234567', 'email' => 'moussa@exemple.test'], $user)), 'user')
        ->create(array_merge(['jeton_suivi_hash' => hash('sha256', $jeton), 'nom_entreprise' => 'Garage Moussa', 'metier' => 'mecanique'], $prestataire));
    Justificatif::factory()->create(['prestataire_id' => $dossier->id, 'type_document' => 'cni']);
    Justificatif::factory()->create(['prestataire_id' => $dossier->id, 'type_document' => 'diplome']);

    return [$dossier, $jeton];
}

describe('GET /suivi/{jeton} (page web)', function () {
    it('affiche un dossier en attente', function () {
        [, $jeton] = dossierPourPage(StatutCompte::EnAttenteValidation);

        $this->get("/suivi/$jeton")->assertOk()
            ->assertSee('Garage Moussa')->assertSee('En cours d\'examen')
            ->assertSee('Carte d\'identité')->assertSee('Diplôme')->assertSee('En attente')
            ->assertSee('lang="fr"', false)->assertSee('noindex', false);
    });

    it('affiche un dossier accepté', function () {
        [$dossier, $jeton] = dossierPourPage(StatutCompte::Actif);
        $dossier->justificatifs()->update(['statut' => StatutJustificatif::Valide->value]);

        $this->get("/suivi/$jeton")->assertOk()
            ->assertSee('Dossier accepté, vous pouvez vous connecter')->assertSee('Validé')->assertDontSee('Motif');
    });

    it('affiche un dossier refusé avec son motif', function () {
        [$dossier, $jeton] = dossierPourPage(StatutCompte::Refuse, ['motif_refus' => 'La photocopie de la CNI est illisible.']);
        $dossier->justificatifs()->where('type_document', 'cni')->update(['statut' => StatutJustificatif::Rejete->value, 'motif_rejet' => 'Image floue']);

        $this->get("/suivi/$jeton")->assertOk()
            ->assertSee('Dossier refusé')->assertSee('La photocopie de la CNI est illisible.')
            ->assertSee('Refusé')->assertSee('Image floue');
    });

    it('n\'affiche le motif de refus que si le dossier est refusé', function () {
        [, $jeton] = dossierPourPage(StatutCompte::EnAttenteValidation, ['motif_refus' => 'Ancien motif oublié']);

        $this->get("/suivi/$jeton")->assertOk()->assertDontSee('Ancien motif oublié');
    });

    it('n\'affiche aucune donnée personnelle', function () {
        [, $jeton] = dossierPourPage(StatutCompte::EnAttenteValidation, [], ['nom' => 'Diopnomsecret', 'prenom' => 'Prenomsecret']);

        $this->get("/suivi/$jeton")->assertOk()
            ->assertDontSee('771234567')->assertDontSee('+221')->assertDontSee('moussa@exemple.test')
            ->assertDontSee('Diopnomsecret')->assertDontSee('Prenomsecret')->assertDontSee($jeton);
    });

    it('échappe le contenu saisi', function () {
        [, $jeton] = dossierPourPage(StatutCompte::EnAttenteValidation, ['nom_entreprise' => '<script>alert(1)</script>']);

        $this->get("/suivi/$jeton")->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    });

    it('renvoie une page 404 propre et identique pour un jeton inconnu ou mal formé', function () {
        $inconnu = $this->get('/suivi/'.Str::random(48));
        $malForme = $this->get('/suivi/abc');

        $inconnu->assertNotFound()->assertSee('Lien de suivi invalide ou expiré');
        $malForme->assertNotFound()->assertSee('Lien de suivi invalide ou expiré');
        expect($inconnu->getContent())->toBe($malForme->getContent());
        $inconnu->assertDontSee('Garage');
    });

    it('envoie les en-têtes de sécurité, y compris sur la 404', function () {
        [, $jeton] = dossierPourPage(StatutCompte::EnAttenteValidation);

        foreach ([$this->get("/suivi/$jeton"), $this->get('/suivi/inconnu')] as $reponse) {
            expect($reponse->headers->get('Cache-Control'))->toContain('no-store')
                ->and($reponse->headers->get('Referrer-Policy'))->toBe('no-referrer')
                ->and($reponse->headers->get('X-Robots-Tag'))->toContain('noindex');
        }
    });

    it('est limitée comme l\'API de suivi (429 au-delà de 30 requêtes par minute)', function () {
        foreach (range(1, 30) as $i) {
            $this->get('/suivi/inconnu')->assertNotFound();
        }

        $this->get('/suivi/inconnu')->assertStatus(429);
    });

    it('ne journalise pas le jeton', function () {
        [, $jeton] = dossierPourPage(StatutCompte::EnAttenteValidation);
        $journal = [];
        Log::listen(function ($message) use (&$journal) {
            $journal[] = $message->message.json_encode($message->context);
        });

        $this->get("/suivi/$jeton")->assertOk();
        $this->get('/suivi/'.$jeton.'faux')->assertNotFound();

        expect(implode("\n", $journal))->not->toContain($jeton);
    });

    it('laisse l\'API JSON de suivi intacte', function () {
        [, $jeton] = dossierPourPage(StatutCompte::EnAttenteValidation);

        $this->getJson("/api/v1/suivi/$jeton")->assertOk()->assertJsonPath('statutCompte', 'en_attente');
        $this->getJson('/api/v1/suivi/inconnu')->assertNotFound()->assertJsonPath('message', 'Lien de suivi invalide ou expiré.');
    });
});
