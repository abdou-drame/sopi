<?php

use App\Enums\StatutCompte;
use App\Models\Avis;
use App\Models\Creneau;
use App\Models\Litige;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\Retrait;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const URL_UTILISATEURS = '/api/v1/admin/utilisateurs';

beforeEach(fn () => Carbon::setTestNow('2030-01-20 12:00:00'));

/** Identifiants (utilisateurs) renvoyés par la liste, dans l'ordre. */
function idsUtilisateurs(string $requete = ''): array
{
    return collect(test()->getJson(URL_UTILISATEURS.$requete)->assertOk()->json('data'))->pluck('id')->all();
}

function prestataireNomme(string $nom, string $prenom, string $entreprise, array $user = []): Prestataire
{
    return Prestataire::factory()
        ->for(User::factory()->prestataire()->state(['nom' => $nom, 'prenom' => $prenom] + $user), 'user')
        ->create(['nom_entreprise' => $entreprise]);
}

describe('utilisateurs — accès', function () {
    it('exige une authentification', function () {
        $this->getJson(URL_UTILISATEURS)->assertUnauthorized();
        $this->getJson(URL_UTILISATEURS.'/'.User::factory()->create()->id)->assertUnauthorized();
    });

    it('refuse un client et un prestataire (403)', function () {
        $cible = User::factory()->create();
        foreach ([User::factory()->create(), User::factory()->prestataire()->create()] as $intrus) {
            $this->actingAs($intrus, 'sanctum');
            $this->getJson(URL_UTILISATEURS)->assertForbidden();
            $this->getJson(URL_UTILISATEURS.'/'.$cible->id)->assertForbidden();
        }
    });
});

describe('utilisateurs — liste', function () {
    it('renvoie une liste vide quand il n\'y a aucun compte client ou prestataire', function () {
        adminConnecte();

        $this->getJson(URL_UTILISATEURS)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    });

    it('renvoie la forme exacte d\'un client et d\'un prestataire', function () {
        adminConnecte();
        $client = User::factory()->create(['nom' => 'Diallo', 'prenom' => 'Awa', 'email' => 'awa@example.com', 'telephone' => '+221770000001', 'created_at' => '2030-01-09 09:00:00']);
        $prestataire = Prestataire::factory()->metier('coiffure')->create([
            'quartier' => 'Malika', 'nom_entreprise' => 'Salon Awa', 'created_at' => '2030-01-10 09:00:00',
        ]);
        $prestataire->user->forceFill(['nom' => 'Diop', 'prenom' => 'Moussa', 'email' => 'moussa@example.com', 'telephone' => '+221770000002', 'created_at' => '2030-01-10 09:00:00'])->save();

        $this->getJson(URL_UTILISATEURS)->assertOk()->assertJsonPath('meta.total', 2)->assertJson([
            'data' => [
                [
                    'id' => $prestataire->user_id, 'role' => 'prestataire', 'prenom' => 'Moussa', 'nom' => 'Diop', 'email' => 'moussa@example.com',
                    'telephone' => '+221770000002', 'statut' => 'actif', 'dateCreation' => '2030-01-10T09:00:00+00:00',
                    'prestataire' => ['id' => $prestataire->id, 'metier' => 'coiffure', 'quartier' => 'Malika', 'nomEntreprise' => 'Salon Awa'],
                ],
                [
                    'id' => $client->id, 'role' => 'client', 'prenom' => 'Awa', 'nom' => 'Diallo', 'email' => 'awa@example.com',
                    'telephone' => '+221770000001', 'statut' => 'actif', 'dateCreation' => '2030-01-09T09:00:00+00:00', 'prestataire' => null,
                ],
            ],
        ]);
    });

    it('ne liste jamais les administrateurs', function () {
        $admin = adminConnecte();
        $client = User::factory()->create();

        expect(idsUtilisateurs())->toBe([$client->id])->not->toContain($admin->id);
    });

    it('trie du plus récent au plus ancien et pagine par 20', function () {
        adminConnecte();
        foreach (range(1, 25) as $i) {
            User::factory()->create(['created_at' => Carbon::parse('2030-01-01 00:00:00')->addMinutes($i)]);
        }

        $page1 = $this->getJson(URL_UTILISATEURS)->assertOk();
        expect($page1->json('data'))->toHaveCount(20)
            ->and($page1->json('meta.total'))->toBe(25)
            ->and($page1->json('data.0.dateCreation'))->toBe('2030-01-01T00:25:00+00:00');

        $page2 = $this->getJson(URL_UTILISATEURS.'?page=2')->assertOk();
        expect($page2->json('data'))->toHaveCount(5)->and($page2->json('data.4.dateCreation'))->toBe('2030-01-01T00:01:00+00:00');
    });

    it('filtre par rôle', function () {
        adminConnecte();
        $client = User::factory()->create();
        $presta = Prestataire::factory()->create();

        expect(idsUtilisateurs('?role=client'))->toBe([$client->id])
            ->and(idsUtilisateurs('?role=prestataire'))->toBe([$presta->user_id]);
    });

    it('filtre par statut du compte', function () {
        adminConnecte();
        User::factory()->create();
        $suspendu = User::factory()->suspendu()->create();
        $attente = Prestataire::factory()->for(User::factory()->prestataire()->statut(StatutCompte::EnAttenteValidation), 'user')->create();

        expect(idsUtilisateurs('?statut=suspendu'))->toBe([$suspendu->id])
            ->and(idsUtilisateurs('?statut=en_attente'))->toBe([$attente->user_id])
            ->and(idsUtilisateurs('?statut=banni'))->toBe([]);
    });

    it('cherche par nom, prénom, nom complet, e-mail, téléphone ou nom d\'entreprise, sans tenir compte de la casse', function () {
        adminConnecte();
        $awa = User::factory()->create(['nom' => 'Diallo', 'prenom' => 'Awa', 'email' => 'awa.diallo@exemple.sn', 'telephone' => '+221771112233']);
        $moussa = prestataireNomme('Ndiaye', 'Moussa', 'Garage Teranga', ['email' => 'moussa@exemple.sn', 'telephone' => '+221779998877']);
        $autre = User::factory()->create(['nom' => 'Sow', 'prenom' => 'Fatou', 'email' => 'fatou@autre.sn', 'telephone' => '+221700000000']);

        expect(idsUtilisateurs('?q=diall'))->toBe([$awa->id])
            ->and(idsUtilisateurs('?q=AWA'))->toBe([$awa->id])
            ->and(idsUtilisateurs('?q='.urlencode('awa diallo')))->toBe([$awa->id])
            ->and(idsUtilisateurs('?q='.urlencode('Diallo Awa')))->toBe([$awa->id])
            ->and(idsUtilisateurs('?q=EXEMPLE.sn'))->toBe([$moussa->user_id, $awa->id])
            ->and(idsUtilisateurs('?q=779998'))->toBe([$moussa->user_id])
            ->and(idsUtilisateurs('?q=teranga'))->toBe([$moussa->user_id])
            ->and(idsUtilisateurs('?q=autre.sn'))->toBe([$autre->id])
            ->and(idsUtilisateurs('?q=introuvable'))->toBe([]);
    });

    it('traite % et _ comme des caractères ordinaires dans la recherche', function () {
        adminConnecte();
        User::factory()->create(['nom' => 'Diallo']);
        $special = User::factory()->create(['nom' => '100%_vrai']);

        expect(idsUtilisateurs('?q='.urlencode('%')))->toBe([$special->id])
            ->and(idsUtilisateurs('?q='.urlencode('_')))->toBe([$special->id]);
    });

    it('filtre par période de création, bornes incluses', function () {
        adminConnecte();
        $avant = User::factory()->create(['created_at' => '2030-01-09 23:59:59']);
        $debut = User::factory()->create(['created_at' => '2030-01-10 00:00:00']);
        $fin = User::factory()->create(['created_at' => '2030-01-12 23:59:59']);
        $apres = User::factory()->create(['created_at' => '2030-01-13 00:00:00']);

        expect(idsUtilisateurs('?debut=2030-01-10&fin=2030-01-12'))->toBe([$fin->id, $debut->id])
            ->and(idsUtilisateurs('?debut=2030-01-13'))->toBe([$apres->id])
            ->and(idsUtilisateurs('?fin=2030-01-09'))->toBe([$avant->id]);
    });

    it('combine les filtres', function () {
        adminConnecte();
        $cible = prestataireNomme('Diop', 'Moussa', 'Garage Diop', ['created_at' => '2030-01-11 10:00:00']);
        prestataireNomme('Diop', 'Ibou', 'Garage Diop 2', ['created_at' => '2030-01-11 10:00:00', 'statut' => StatutCompte::Suspendu]);
        User::factory()->create(['nom' => 'Diop', 'created_at' => '2030-01-11 10:00:00']);
        prestataireNomme('Diop', 'Vieux', 'Garage Vieux', ['created_at' => '2029-12-01 10:00:00']);

        expect(idsUtilisateurs('?role=prestataire&statut=actif&q=diop&debut=2030-01-01&fin=2030-01-31'))->toBe([$cible->user_id]);
    });

    it('rejette des filtres invalides en français (422)', function (string $requete, string $champ, string $message) {
        adminConnecte();

        $this->getJson(URL_UTILISATEURS.$requete)->assertUnprocessable()->assertJsonPath('message', $message)->assertJsonPath("errors.$champ.0", $message);
    })->with([
        'rôle' => ['?role=administrateur', 'role', 'Le rôle doit être client ou prestataire.'],
        'statut' => ['?statut=inconnu', 'statut', 'Le statut doit être en_attente, actif, refuse, suspendu ou banni.'],
        'début' => ['?debut=10/01/2030', 'debut', 'La date de début doit être au format AAAA-MM-JJ.'],
        'fin' => ['?fin=demain', 'fin', 'La date de fin doit être au format AAAA-MM-JJ.'],
        'période inversée' => ['?debut=2030-02-01&fin=2030-01-01', 'fin', 'La date de fin doit être postérieure ou égale à la date de début.'],
        'page' => ['?page=0', 'page', 'La page doit être au moins 1.'],
        'recherche trop longue' => ['?q='.'a'.'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'q', 'La recherche ne peut pas dépasser 100 caractères.'],
    ]);

    it('n\'expose aucune donnée sensible', function () {
        adminConnecte();
        $presta = Prestataire::factory()->create(['jeton_suivi_hash' => hash('sha256', 'secret-de-suivi')]);
        $presta->user->forceFill(['jeton_push' => 'jeton-push-confidentiel'])->save();
        $hash = $presta->user->password;
        User::factory()->create();

        $reponse = $this->getJson(URL_UTILISATEURS)->assertOk();
        $corps = $reponse->getContent();

        foreach (['password', 'motDePasse', 'jeton', 'token', 'remember', 'push', 'suivi', 'justificatif', 'photo', $hash, 'jeton-push-confidentiel', 'secret-de-suivi'] as $interdit) {
            expect(strtolower($corps))->not->toContain(strtolower($interdit));
        }
        expect(array_keys($reponse->json('data.0')))->toBe(['id', 'role', 'prenom', 'nom', 'email', 'telephone', 'statut', 'dateCreation', 'prestataire']);
    });

    it('exécute un nombre de requêtes constant quel que soit le volume', function () {
        adminConnecte();
        Prestataire::factory()->create();
        User::factory()->create();
        $petit = nombreDeRequetes(fn () => $this->getJson(URL_UTILISATEURS.'?q=a')->assertOk());

        Prestataire::factory()->count(8)->create();
        User::factory()->count(8)->create();
        $grand = nombreDeRequetes(fn () => $this->getJson(URL_UTILISATEURS.'?q=a')->assertOk());

        expect($grand)->toBe($petit);
    });
});

describe('utilisateurs — fiche', function () {
    it('répond 404 pour un identifiant inconnu, mal formé ou celui d\'un administrateur', function () {
        $admin = adminConnecte();
        $autreAdmin = User::factory()->administrateur()->create();

        foreach (['0198a1b2-0000-7000-8000-00000000ffff', 'pas-un-uuid', $admin->id, $autreAdmin->id] as $id) {
            $this->getJson(URL_UTILISATEURS.'/'.$id)->assertNotFound()->assertExactJson(['message' => 'Utilisateur introuvable.']);
        }
    });

    it('renvoie la fiche exacte d\'un client', function () {
        adminConnecte();
        $client = User::factory()->create(['nom' => 'Diallo', 'prenom' => 'Awa', 'email' => null, 'telephone' => '+221770000001', 'created_at' => '2030-01-09 09:00:00']);
        Reservation::factory()->terminee()->count(2)->create(['client_id' => $client->id]);
        Reservation::factory()->annulee()->create(['client_id' => $client->id]);
        Reservation::factory()->terminee()->create(); // celle d'un autre client
        Avis::factory()->create(['reservation_id' => Reservation::factory()->terminee()->create(['client_id' => $client->id])->id]);
        Litige::factory()->create(['reservation_id' => Reservation::factory()->confirmee()->create(['client_id' => $client->id])->id]);
        Litige::factory()->cloture()->create(['reservation_id' => Reservation::factory()->terminee()->create(['client_id' => $client->id])->id]);
        Sanction::factory()->create(['user_id' => $client->id]);
        Sanction::factory()->levee()->create(['user_id' => $client->id]);

        $this->getJson(URL_UTILISATEURS.'/'.$client->id)->assertOk()->assertExactJson([
            'id' => $client->id, 'role' => 'client', 'prenom' => 'Awa', 'nom' => 'Diallo', 'email' => null, 'telephone' => '+221770000001',
            'statut' => 'actif', 'dateCreation' => '2030-01-09T09:00:00+00:00', 'prestataire' => null,
            'sanctionActive' => ['id' => Sanction::where('user_id', $client->id)->whereNull('levee_le')->value('id'), 'type' => 'avertissement', 'dateFin' => null],
            'nombreSanctions' => 2,
            'client' => [
                'reservationsParStatut' => ['en_attente' => 0, 'acceptee' => 0, 'confirmee' => 1, 'en_cours' => 0, 'terminee' => 4, 'refusee' => 0, 'annulee' => 1],
                'nombreAvisDonnes' => 1,
                'litiges' => ['total' => 2, 'ouverts' => 1],
            ],
        ]);
    });

    it('renvoie la fiche exacte d\'un prestataire, avis masqués exclus de la note', function () {
        adminConnecte();
        $presta = Prestataire::factory()->metier('mecanique')->avoirSolde(12000)->create(['quartier' => 'Malika', 'nom_entreprise' => 'Garage Exemple', 'adresse' => 'Rue 10']);
        $presta->user->forceFill(['nom' => 'Diop', 'prenom' => 'Moussa', 'email' => 'moussa@example.com', 'telephone' => '+221770000002', 'created_at' => '2030-01-10 09:00:00', 'statut' => StatutCompte::Suspendu])->save();
        Prestation::factory()->for($presta)->count(2)->create();
        Prestation::factory()->for($presta)->inactive()->create();
        $creneau = fn () => Creneau::factory()->for($presta)->create();
        Reservation::factory()->terminee()->create(['creneau_id' => $creneau()->id]);
        $terminee = Reservation::factory()->terminee()->create(['creneau_id' => $creneau()->id]);
        $masquee = Reservation::factory()->terminee()->create(['creneau_id' => $creneau()->id]);
        Reservation::factory()->enAttente()->create(['creneau_id' => $creneau()->id]);
        Reservation::factory()->terminee()->create(); // autre prestataire
        Avis::factory()->note(5)->create(['reservation_id' => $terminee->id]);
        $avisMasque = Avis::factory()->note(1)->create(['reservation_id' => $masquee->id]);
        DB::table('avis')->where('id', $avisMasque->id)->update(['signale_le' => now(), 'motif_signalement' => 'Injurieux', 'modere_le' => now(), 'modere_par_id' => User::factory()->administrateur()->create()->id, 'masque_le' => now()]);
        Retrait::factory()->count(3)->create(['prestataire_id' => $presta->id]);
        Litige::factory()->create(['reservation_id' => $terminee->id, 'prestataire_id' => $presta->id]);
        $suspension = Sanction::factory()->suspension(Carbon::parse('2030-02-01 09:00:00'))->create(['user_id' => $presta->user_id]);
        Sanction::factory()->create(['user_id' => $presta->user_id]);

        $this->getJson(URL_UTILISATEURS.'/'.$presta->user_id)->assertOk()->assertExactJson([
            'id' => $presta->user_id, 'role' => 'prestataire', 'prenom' => 'Moussa', 'nom' => 'Diop', 'email' => 'moussa@example.com',
            'telephone' => '+221770000002', 'statut' => 'suspendu', 'dateCreation' => '2030-01-10T09:00:00+00:00',
            'prestataire' => [
                'id' => $presta->id, 'metier' => 'mecanique', 'quartier' => 'Malika', 'nomEntreprise' => 'Garage Exemple',
                'adresse' => 'Rue 10', 'solde' => 12000, 'prestationsActives' => 6, // 2 créées + 4 par les fabriques de réservations
                'reservationsParStatut' => ['en_attente' => 1, 'acceptee' => 0, 'confirmee' => 0, 'en_cours' => 0, 'terminee' => 3, 'refusee' => 0, 'annulee' => 0],
                'noteMoyenne' => 5.0, 'nombreAvis' => 1, 'nombreRetraits' => 3, 'litiges' => ['total' => 1, 'ouverts' => 1],
            ],
            'sanctionActive' => ['id' => $suspension->id, 'type' => 'suspension_temporaire', 'dateFin' => '2030-02-01T09:00:00+00:00'],
            'nombreSanctions' => 2,
            'client' => null,
        ]);
    });

    it('renvoie noteMoyenne nulle sans avis et aucune sanction active', function () {
        adminConnecte();
        $presta = Prestataire::factory()->create();
        Sanction::factory()->levee()->create(['user_id' => $presta->user_id]);

        $this->getJson(URL_UTILISATEURS.'/'.$presta->user_id)->assertOk()
            ->assertJsonPath('prestataire.noteMoyenne', null)
            ->assertJsonPath('prestataire.nombreAvis', 0)
            ->assertJsonPath('prestataire.solde', 0)
            ->assertJsonPath('sanctionActive', null)
            ->assertJsonPath('nombreSanctions', 1);
    });

    it('place le blocage avant un avertissement non levé', function () {
        adminConnecte();
        $client = User::factory()->create();
        Sanction::factory()->create(['user_id' => $client->id]);
        $bannissement = Sanction::factory()->bannissement()->create(['user_id' => $client->id]);

        $this->getJson(URL_UTILISATEURS.'/'.$client->id)->assertOk()->assertJsonPath('sanctionActive.id', $bannissement->id)->assertJsonPath('sanctionActive.type', 'bannissement_definitif');
    });

    it('n\'expose aucune donnée sensible', function () {
        adminConnecte();
        $presta = Prestataire::factory()->create(['jeton_suivi_hash' => hash('sha256', 'secret-de-suivi')]);
        $presta->user->forceFill(['jeton_push' => 'jeton-push-confidentiel'])->save();

        $corps = strtolower($this->getJson(URL_UTILISATEURS.'/'.$presta->user_id)->assertOk()->getContent());

        foreach (['password', 'jeton', 'token', 'remember', 'push', 'suivi', 'justificatif', 'photo', strtolower($presta->user->password), 'secret-de-suivi'] as $interdit) {
            expect($corps)->not->toContain($interdit);
        }
    });

    it('exécute un nombre de requêtes constant quel que soit le volume', function () {
        adminConnecte();
        $presta = Prestataire::factory()->create();
        $client = User::factory()->create();
        $petit = [
            nombreDeRequetes(fn () => $this->getJson(URL_UTILISATEURS.'/'.$presta->user_id)->assertOk()),
            nombreDeRequetes(fn () => $this->getJson(URL_UTILISATEURS.'/'.$client->id)->assertOk()),
        ];

        foreach (range(1, 4) as $i) {
            $reservation = Reservation::factory()->terminee()->create(['creneau_id' => Creneau::factory()->for($presta)->create()->id, 'client_id' => $client->id]);
            Avis::factory()->create(['reservation_id' => $reservation->id]);
            Retrait::factory()->create(['prestataire_id' => $presta->id]);
            Sanction::factory()->levee()->create(['user_id' => $presta->user_id]);
        }
        $grand = [
            nombreDeRequetes(fn () => $this->getJson(URL_UTILISATEURS.'/'.$presta->user_id)->assertOk()),
            nombreDeRequetes(fn () => $this->getJson(URL_UTILISATEURS.'/'.$client->id)->assertOk()),
        ];

        expect($grand)->toBe($petit);
    });
});
