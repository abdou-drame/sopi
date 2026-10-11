<?php

use App\Enums\StatutCompte;
use App\Models\Justificatif;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Crée un dossier avec un jeton connu (seul le hachage va en base) et renvoie [prestataire, jeton]. */
function dossierAvecJeton(?Closure $modifier = null): array
{
    $jeton = Str::random(48);
    $factory = Prestataire::factory();
    if ($modifier) {
        $factory = $modifier($factory);
    } else {
        $factory = $factory->for(User::factory()->prestataire()->statut(StatutCompte::EnAttenteValidation), 'user');
    }

    $prestataire = $factory->create(['jeton_suivi_hash' => hash('sha256', $jeton), 'nom_entreprise' => 'Garage Moussa']);
    Justificatif::factory()->create(['prestataire_id' => $prestataire->id, 'type_document' => 'cni']);
    Justificatif::factory()->create(['prestataire_id' => $prestataire->id, 'type_document' => 'diplome']);

    return [$prestataire, $jeton];
}

describe('GET /suivi/{jeton}', function () {
    it('renvoie le dossier en attente, sans authentification', function () {
        [, $jeton] = dossierAvecJeton();

        $this->getJson("/api/v1/suivi/$jeton")
            ->assertOk()
            ->assertExactJsonStructure([
                'nomEntreprise', 'metier', 'statutCompte', 'motifRefus', 'dateSoumission',
                'justificatifs' => ['*' => ['typeDocument', 'statut']],
            ])
            ->assertJsonPath('nomEntreprise', 'Garage Moussa')
            ->assertJsonPath('statutCompte', 'en_attente')
            ->assertJsonPath('motifRefus', null)
            ->assertJsonCount(2, 'justificatifs')
            ->assertJsonPath('justificatifs.0', ['typeDocument' => 'cni', 'statut' => 'en_attente']);
    });

    it('renvoie le statut actif après validation', function () {
        [$prestataire, $jeton] = dossierAvecJeton();
        $prestataire->user->forceFill(['statut' => StatutCompte::Actif])->save();

        $this->getJson("/api/v1/suivi/$jeton")
            ->assertOk()
            ->assertJsonPath('statutCompte', 'actif')
            ->assertJsonPath('motifRefus', null);
    });

    it('renvoie le statut refusé avec son motif', function () {
        [, $jeton] = dossierAvecJeton(fn ($f) => $f->refuse('Pièce d\'identité illisible.'));

        $this->getJson("/api/v1/suivi/$jeton")
            ->assertOk()
            ->assertJsonPath('statutCompte', 'refuse')
            ->assertJsonPath('motifRefus', 'Pièce d\'identité illisible.');
    });

    it('ne renvoie le motif que pour un dossier refusé', function () {
        [$prestataire, $jeton] = dossierAvecJeton();
        $prestataire->forceFill(['motif_refus' => 'Ancien motif'])->save();

        $this->getJson("/api/v1/suivi/$jeton")->assertOk()->assertJsonPath('motifRefus', null);
    });

    it('renvoie le même 404 pour tout jeton inconnu', function (string $jeton) {
        dossierAvecJeton();

        $this->getJson('/api/v1/suivi/'.$jeton)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Lien de suivi invalide ou expiré.']);
    })->with(['inconnu', 'x', 'JETON-DE-SUIVI-INCONNU-0123456789-abcdefghijklmnopqr']);

    it('ne reconnaît pas le hachage lui-même comme jeton', function () {
        [$prestataire] = dossierAvecJeton();

        $this->getJson('/api/v1/suivi/'.$prestataire->jeton_suivi_hash)
            ->assertNotFound()
            ->assertJsonPath('message', 'Lien de suivi invalide ou expiré.');
    });

    it('ne divulgue aucune donnée personnelle ni identifiant interne', function () {
        [$prestataire, $jeton] = dossierAvecJeton();
        $justificatif = $prestataire->justificatifs()->first();

        $brut = $this->getJson("/api/v1/suivi/$jeton")->assertOk()->getContent();

        foreach ([
            $prestataire->user->telephone, $prestataire->user->email, $prestataire->user->nom, $prestataire->user->prenom,
            $prestataire->adresse, $prestataire->quartier, $prestataire->id, $prestataire->user_id, $justificatif->id,
            $justificatif->chemin_fichier, $prestataire->jeton_suivi_hash, $jeton, 'chemin', 'justificatifs/',
        ] as $secret) {
            expect($brut)->not->toContain((string) $secret);
        }
    });

    it("n'écrit jamais le jeton dans les journaux", function () {
        $journal = tempnam(sys_get_temp_dir(), 'sopi-log');
        config([
            'logging.default' => 'test_suivi',
            'logging.channels.test_suivi' => ['driver' => 'single', 'path' => $journal, 'level' => 'debug'],
        ]);
        [, $jeton] = dossierAvecJeton();

        $this->getJson("/api/v1/suivi/$jeton")->assertOk();
        $this->getJson('/api/v1/suivi/jeton-inconnu-0123456789')->assertNotFound();

        expect(file_get_contents($journal))->not->toContain($jeton)->not->toContain('jeton-inconnu-0123456789');
        unlink($journal);
    });

    it('limite à 30 requêtes par minute et par IP', function () {
        foreach (range(1, 30) as $ignore) {
            $this->getJson('/api/v1/suivi/jeton-inconnu')->assertNotFound();
        }

        $this->getJson('/api/v1/suivi/jeton-inconnu')
            ->assertStatus(429)
            ->assertJsonPath('message', 'Trop de tentatives. Réessayez dans quelques instants.');
    });
});
