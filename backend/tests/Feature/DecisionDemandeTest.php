<?php

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\Justificatif;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Demande en attente avec deux justificatifs et un jeton de suivi connu. Renvoie [prestataire, jeton]. */
function demandeEnAttenteAvecJeton(): array
{
    $jeton = Str::random(48);
    $prestataire = Prestataire::factory()
        ->for(User::factory()->prestataire()->statut(StatutCompte::EnAttenteValidation)->state(['password' => 'mot-de-passe-exemple']), 'user')
        ->create(['jeton_suivi_hash' => hash('sha256', $jeton)]);
    Justificatif::factory()->count(2)->create(['prestataire_id' => $prestataire->id]);

    return [$prestataire, $jeton];
}

function connexionPrestataire(User $user)
{
    return test()->postJson('/api/v1/auth/connexion', ['telephone' => $user->telephone, 'password' => 'mot-de-passe-exemple']);
}

describe('accès réservé à l\'administrateur', function () {
    it('renvoie 401 sans jeton, 403 pour client et prestataire', function (string $action) {
        [$prestataire] = demandeEnAttenteAvecJeton();
        $url = "/api/v1/admin/prestataires/{$prestataire->id}/$action";
        $corps = ['motif' => 'Motif de refus suffisant.'];

        $this->postJson($url, $corps)->assertUnauthorized();
        foreach ([Role::Client, Role::Prestataire] as $role) {
            $this->actingAs(User::factory()->state(['role' => $role])->create(), 'sanctum')
                ->postJson($url, $corps)->assertForbidden();
        }

        expect($prestataire->user->fresh()->statut)->toBe(StatutCompte::EnAttenteValidation);
    })->with(['accepter', 'refuser']);
});

describe('POST /admin/prestataires/{id}/accepter', function () {
    it('active le compte, valide les justificatifs et enregistre le vérificateur', function () {
        [$prestataire] = demandeEnAttenteAvecJeton();
        $prestataire->forceFill(['motif_refus' => 'Ancien motif'])->save();
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/prestataires/{$prestataire->id}/accepter")
            ->assertOk()
            ->assertJsonPath('id', $prestataire->id)
            ->assertJsonPath('statutCompte', 'actif')
            ->assertJsonPath('motifRefus', null)
            ->assertJsonCount(2, 'justificatifs')
            ->assertJsonPath('justificatifs.0.statut', 'valide')
            ->assertJsonPath('justificatifs.1.statut', 'valide');

        expect($prestataire->user->fresh()->statut)->toBe(StatutCompte::Actif)
            ->and($prestataire->fresh()->motif_refus)->toBeNull();
        foreach ($prestataire->justificatifs()->get() as $justificatif) {
            expect($justificatif->statut->value)->toBe('valide')->and($justificatif->verifie_par)->toBe($admin->id);
        }
    });

    it('permet ensuite la connexion du prestataire', function () {
        [$prestataire] = demandeEnAttenteAvecJeton();
        connexionPrestataire($prestataire->user)->assertForbidden();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson("/api/v1/admin/prestataires/{$prestataire->id}/accepter")->assertOk();
        app('auth')->forgetGuards();

        connexionPrestataire($prestataire->user)->assertOk()->assertJsonStructure(['token'])
            ->assertJsonPath('user.role', 'prestataire');
    });

    it('met à jour le lien de suivi', function () {
        [$prestataire, $jeton] = demandeEnAttenteAvecJeton();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson("/api/v1/admin/prestataires/{$prestataire->id}/accepter")->assertOk();
        app('auth')->forgetGuards();

        $this->getJson("/api/v1/suivi/$jeton")->assertOk()
            ->assertJsonPath('statutCompte', 'actif')->assertJsonPath('motifRefus', null)
            ->assertJsonPath('justificatifs.0.statut', 'valide');
    });
});

describe('POST /admin/prestataires/{id}/refuser', function () {
    it('refuse le compte, rejette les justificatifs et enregistre le motif', function () {
        [$prestataire] = demandeEnAttenteAvecJeton();
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/prestataires/{$prestataire->id}/refuser", ['motif' => '  La CNI est illisible, merci de renvoyer le dossier.  '])
            ->assertOk()
            ->assertJsonPath('statutCompte', 'refuse')
            ->assertJsonPath('motifRefus', 'La CNI est illisible, merci de renvoyer le dossier.')
            ->assertJsonPath('justificatifs.0.statut', 'rejete')
            ->assertJsonPath('justificatifs.1.statut', 'rejete');

        expect($prestataire->user->fresh()->statut)->toBe(StatutCompte::Refuse)
            ->and($prestataire->fresh()->motif_refus)->toBe('La CNI est illisible, merci de renvoyer le dossier.');
        foreach ($prestataire->justificatifs()->get() as $justificatif) {
            expect($justificatif->statut->value)->toBe('rejete')->and($justificatif->verifie_par)->toBe($admin->id);
        }
    });

    it('refuse un motif absent, vide, trop court ou trop long', function (mixed $motif) {
        [$prestataire] = demandeEnAttenteAvecJeton();
        $corps = $motif === null ? [] : ['motif' => $motif];

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson("/api/v1/admin/prestataires/{$prestataire->id}/refuser", $corps)
            ->assertUnprocessable()->assertJsonValidationErrors('motif');

        expect($prestataire->user->fresh()->statut)->toBe(StatutCompte::EnAttenteValidation);
    })->with([null, '', '         ', 'Trop cou', fn () => str_repeat('a', 501)]);

    it('accepte un motif de 10 et de 500 caractères', function (int $longueur) {
        [$prestataire] = demandeEnAttenteAvecJeton();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson("/api/v1/admin/prestataires/{$prestataire->id}/refuser", ['motif' => str_repeat('a', $longueur)])
            ->assertOk();
    })->with([10, 500]);

    it('renvoie les messages en français', function () {
        [$prestataire] = demandeEnAttenteAvecJeton();
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/prestataires/{$prestataire->id}/refuser", [])
            ->assertJsonPath('errors.motif.0', 'Le motif du refus est obligatoire.');
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/prestataires/{$prestataire->id}/refuser", ['motif' => 'court'])
            ->assertJsonPath('errors.motif.0', 'Le motif du refus doit contenir au moins 10 caractères.');
    });

    it('bloque la connexion du prestataire refusé avec le 403 en français', function () {
        [$prestataire] = demandeEnAttenteAvecJeton();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson("/api/v1/admin/prestataires/{$prestataire->id}/refuser", ['motif' => 'Dossier incomplet, pièces illisibles.'])->assertOk();
        app('auth')->forgetGuards();

        connexionPrestataire($prestataire->user)->assertForbidden()
            ->assertJsonPath('message', "Votre demande d'inscription a été refusée.")->assertJsonMissingPath('token');
    });

    it('met à jour le lien de suivi avec le motif', function () {
        [$prestataire, $jeton] = demandeEnAttenteAvecJeton();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson("/api/v1/admin/prestataires/{$prestataire->id}/refuser", ['motif' => 'Dossier incomplet, pièces illisibles.'])->assertOk();
        app('auth')->forgetGuards();

        $this->getJson("/api/v1/suivi/$jeton")->assertOk()
            ->assertJsonPath('statutCompte', 'refuse')
            ->assertJsonPath('motifRefus', 'Dossier incomplet, pièces illisibles.');
    });
});

describe('règles communes', function () {
    it('renvoie 409 pour une demande déjà traitée, sans rien modifier', function (string $premiere, string $seconde) {
        [$prestataire] = demandeEnAttenteAvecJeton();
        $admin = User::factory()->administrateur()->create();
        $corps = ['motif' => 'Dossier incomplet, pièces illisibles.'];

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/prestataires/{$prestataire->id}/$premiere", $corps)->assertOk();
        $apres = $prestataire->user->fresh()->statut;

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/prestataires/{$prestataire->id}/$seconde", $corps)
            ->assertStatus(409)->assertExactJson(['message' => 'Cette demande a déjà été traitée.']);

        expect($prestataire->user->fresh()->statut)->toBe($apres);
    })->with([['accepter', 'accepter'], ['accepter', 'refuser'], ['refuser', 'accepter'], ['refuser', 'refuser']]);

    it('renvoie 409 pour un prestataire déjà actif (non issu d\'une demande en attente)', function () {
        $prestataire = Prestataire::factory()->create();

        $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson("/api/v1/admin/prestataires/{$prestataire->id}/accepter")->assertStatus(409);
    });

    it('renvoie 404 pour une demande inexistante', function (string $action) {
        $admin = User::factory()->administrateur()->create();
        $corps = ['motif' => 'Motif de refus suffisant.'];

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/prestataires/'.fake()->uuid()."/$action", $corps)
            ->assertNotFound()->assertJsonPath('message', 'Demande introuvable.');
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/prestataires/pas-un-uuid/$action", $corps)->assertNotFound();
    })->with(['accepter', 'refuser']);

    it('ne renvoie jamais de chemin de stockage', function () {
        [$prestataire] = demandeEnAttenteAvecJeton();

        $brut = $this->actingAs(User::factory()->administrateur()->create(), 'sanctum')
            ->postJson("/api/v1/admin/prestataires/{$prestataire->id}/accepter")->assertOk()->getContent();

        expect($brut)->not->toContain('chemin_fichier')->not->toContain('justificatifs/');
    });
});
