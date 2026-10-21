<?php

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\Justificatif;
use App\Models\Prestataire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

const CONTENU_PDF_ADMIN = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

function admin(): User
{
    return User::factory()->administrateur()->create();
}

function demande(StatutCompte $statut = StatutCompte::EnAttenteValidation, array $attributs = []): Prestataire
{
    return Prestataire::factory()
        ->for(User::factory()->prestataire()->statut($statut), 'user')
        ->create($attributs);
}

/** Justificatif avec un vrai fichier dans le faux disque privé. */
function justificatifAvecFichier(Prestataire $prestataire, string $nomOriginal = 'ma-cni.pdf'): Justificatif
{
    $chemin = "justificatifs/{$prestataire->id}/".fake()->uuid().'.pdf';
    Storage::disk('local')->put($chemin, CONTENU_PDF_ADMIN);

    return Justificatif::factory()->create([
        'prestataire_id' => $prestataire->id,
        'type_document' => 'cni',
        'chemin_fichier' => $chemin,
        'nom_original' => $nomOriginal,
        'type_mime' => 'application/pdf',
        'taille' => strlen(CONTENU_PDF_ADMIN),
    ]);
}

describe('accès réservé à l\'administrateur', function () {
    it('refuse sans jeton (401), client et prestataire (403), accepte l\'administrateur (200)', function () {
        $prestataire = demande();
        $justificatif = justificatifAvecFichier($prestataire);
        $urls = [
            '/api/v1/admin/prestataires/demandes',
            "/api/v1/admin/prestataires/{$prestataire->id}",
            "/api/v1/admin/justificatifs/{$justificatif->id}/telecharger",
        ];

        foreach ($urls as $url) {
            $this->getJson($url)->assertUnauthorized()->assertJsonPath('message', 'Authentification requise.');
        }

        foreach ($urls as $url) {
            foreach ([Role::Client, Role::Prestataire] as $role) {
                $this->actingAs(User::factory()->state(['role' => $role])->create(), 'sanctum')
                    ->getJson($url)->assertForbidden()
                    ->assertJsonPath('message', "Vous n'avez pas le droit d'effectuer cette action.");
            }
            $this->actingAs(admin(), 'sanctum')->get($url)->assertOk();
        }
    });
});

describe('GET /admin/prestataires/demandes', function () {
    it('liste par défaut les demandes en attente, de la plus récente à la plus ancienne', function () {
        $recente = demande(attributs: ['nom_entreprise' => 'Récente', 'created_at' => now()->subHour()]);
        $ancienne = demande(attributs: ['nom_entreprise' => 'Ancienne', 'created_at' => now()->subDays(2)]);
        demande(StatutCompte::Actif, ['nom_entreprise' => 'Déjà actif']);
        Justificatif::factory()->count(2)->create(['prestataire_id' => $ancienne->id]);

        $reponse = $this->actingAs(admin(), 'sanctum')->getJson('/api/v1/admin/prestataires/demandes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nomEntreprise', 'Récente')
            ->assertJsonPath('data.1.nomEntreprise', 'Ancienne')
            ->assertJsonPath('data.1.id', $ancienne->id)
            ->assertJsonPath('data.0.statutCompte', 'en_attente')
            ->assertJsonPath('data.1.nombreJustificatifs', 2)
            ->assertJsonPath('data.0.nombreJustificatifs', 0)
            ->assertJsonStructure(['data' => [['id', 'nom', 'prenom', 'nomEntreprise', 'metier', 'quartier', 'statutCompte', 'dateSoumission', 'nombreJustificatifs']]]);

        expect($reponse->json('data.0'))->not->toHaveKeys(['telephone', 'email', 'adresse']);
    });

    it('filtre par statut', function (string $filtre, int $attendus) {
        demande(StatutCompte::EnAttenteValidation);
        demande(StatutCompte::Actif);
        demande(StatutCompte::Actif);
        demande(StatutCompte::Refuse);

        $this->actingAs(admin(), 'sanctum')->getJson("/api/v1/admin/prestataires/demandes?statut=$filtre")
            ->assertOk()->assertJsonCount($attendus, 'data');
    })->with([['en_attente', 1], ['actif', 2], ['refuse', 1], ['tous', 4]]);

    it('refuse un statut inconnu', function () {
        $this->actingAs(admin(), 'sanctum')->getJson('/api/v1/admin/prestataires/demandes?statut=nimporte')
            ->assertUnprocessable()->assertJsonValidationErrors('statut');
    });

    it('pagine par 20', function () {
        foreach (range(1, 25) as $ignore) {
            demande();
        }

        $admin = admin();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/prestataires/demandes')
            ->assertOk()->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)->assertJsonPath('meta.per_page', 20)->assertJsonPath('meta.last_page', 2);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/prestataires/demandes?page=2')
            ->assertOk()->assertJsonCount(5, 'data');
    });
});

describe('GET /admin/prestataires/{id}', function () {
    it('renvoie le détail avec coordonnées et justificatifs, sans chemin de stockage', function () {
        $prestataire = demande(attributs: ['adresse' => 'Rue 10, Malika']);
        $justificatif = justificatifAvecFichier($prestataire, 'ma-cni.pdf');

        $reponse = $this->actingAs(admin(), 'sanctum')->getJson("/api/v1/admin/prestataires/{$prestataire->id}")
            ->assertOk()
            ->assertJsonPath('telephone', $prestataire->user->telephone)
            ->assertJsonPath('email', $prestataire->user->email)
            ->assertJsonPath('adresse', 'Rue 10, Malika')
            ->assertJsonPath('motifRefus', null)
            ->assertJsonPath('justificatifs.0', [
                'id' => $justificatif->id, 'typeDocument' => 'cni', 'statut' => 'en_attente',
                'nomOriginal' => 'ma-cni.pdf', 'typeMime' => 'application/pdf', 'taille' => strlen(CONTENU_PDF_ADMIN),
            ]);

        expect($reponse->getContent())->not->toContain('chemin')->not->toContain('justificatifs/')
            ->not->toContain($justificatif->chemin_fichier)->not->toContain('jeton_suivi')->not->toContain('password');
    });

    it('renvoie le motif de refus', function () {
        $prestataire = Prestataire::factory()->refuse('Documents illisibles.')->create();

        $this->actingAs(admin(), 'sanctum')->getJson("/api/v1/admin/prestataires/{$prestataire->id}")
            ->assertOk()->assertJsonPath('statutCompte', 'refuse')->assertJsonPath('motifRefus', 'Documents illisibles.');
    });

    it('renvoie 404 en français pour une demande inconnue ou un identifiant invalide', function () {
        $this->actingAs(admin(), 'sanctum')->getJson('/api/v1/admin/prestataires/'.fake()->uuid())
            ->assertNotFound()->assertJsonPath('message', 'Demande introuvable.');
        $this->actingAs(admin(), 'sanctum')->getJson('/api/v1/admin/prestataires/pas-un-uuid')->assertNotFound();
    });
});

describe('GET /admin/justificatifs/{id}/telecharger', function () {
    it('renvoie le contenu identique au fichier stocké, avec les bons en-têtes', function () {
        $justificatif = justificatifAvecFichier(demande(), 'ma-cni.pdf');

        $reponse = $this->actingAs(admin(), 'sanctum')->get("/api/v1/admin/justificatifs/{$justificatif->id}/telecharger")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        expect($reponse->streamedContent())->toBe(CONTENU_PDF_ADMIN)
            ->and($reponse->headers->get('Content-Disposition'))->toStartWith('inline')->toContain('ma-cni.pdf')
            ->and($reponse->headers->get('Cache-Control'))->toContain('private');
    });

    it('nettoie le nom de fichier', function () {
        $justificatif = justificatifAvecFichier(demande(), "..\\..\\etc/pass\"wd;\r\n.pdf");

        $disposition = $this->actingAs(admin(), 'sanctum')->get("/api/v1/admin/justificatifs/{$justificatif->id}/telecharger")
            ->assertOk()->headers->get('Content-Disposition');

        expect($disposition)->not->toContain('..')->not->toContain('etc/')->not->toContain("\n")->not->toContain("\r");
    });

    it('renvoie 404 en français quand le fichier physique manque', function () {
        $justificatif = justificatifAvecFichier(demande());
        Storage::disk('local')->delete($justificatif->chemin_fichier);

        $this->actingAs(admin(), 'sanctum')->getJson("/api/v1/admin/justificatifs/{$justificatif->id}/telecharger")
            ->assertNotFound()->assertJsonPath('message', 'Fichier introuvable.');
    });

    it('renvoie 404 en français pour un justificatif inconnu', function () {
        $this->actingAs(admin(), 'sanctum')->getJson('/api/v1/admin/justificatifs/'.fake()->uuid().'/telecharger')
            ->assertNotFound()->assertJsonPath('message', 'Justificatif introuvable.');
    });

    it("n'est pas accessible publiquement dans le stockage", function () {
        $justificatif = justificatifAvecFichier(demande());

        $this->get('/storage/'.$justificatif->chemin_fichier)->assertNotFound();
        expect(config('filesystems.disks.local.serve'))->toBeFalse();
    });
});
