<?php

use App\Enums\Metier;
use App\Enums\StatutCompte;
use App\Models\Prestataire;
use App\Models\Prestation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const URL_RECHERCHE = '/api/v1/prestataires';

/** Prestataire visible (compte actif) avec une prestation active au prix donné. */
function prestataireRecherche(array $attributs = [], float $prix = 10000, StatutCompte $statut = StatutCompte::Actif, ?Metier $metier = null): Prestataire
{
    $factory = Prestataire::factory()->for(User::factory()->prestataire()->statut($statut), 'user');
    if ($metier) {
        $factory = $factory->metier($metier);
    }

    $prestataire = $factory->create($attributs);
    Prestation::factory()->create(['prestataire_id' => $prestataire->id, 'prix' => $prix]);

    return $prestataire;
}

function chercher(array $params = []): TestResponse
{
    Sanctum::actingAs(User::factory()->create());

    return test()->getJson(URL_RECHERCHE.'?'.http_build_query($params));
}

function nomsTrouves(TestResponse $reponse): array
{
    return collect($reponse->json('data'))->pluck('nomEntreprise')->all();
}

it('refuse sans jeton', function () {
    $this->getJson(URL_RECHERCHE)->assertUnauthorized();
});

it('refuse un prestataire et un administrateur', function () {
    Sanctum::actingAs(User::factory()->prestataire()->create());
    $this->getJson(URL_RECHERCHE)->assertForbidden();

    Sanctum::actingAs(User::factory()->administrateur()->create());
    $this->getJson(URL_RECHERCHE)->assertForbidden();
});

it('ne liste que les comptes actifs ayant une prestation active', function () {
    prestataireRecherche(['nom_entreprise' => 'Visible']);

    foreach ([StatutCompte::EnAttenteValidation, StatutCompte::Refuse, StatutCompte::Suspendu, StatutCompte::Banni] as $statut) {
        prestataireRecherche(['nom_entreprise' => 'Exclu '.$statut->value], 10000, $statut);
    }

    Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create(['nom_entreprise' => 'Sans prestation']);

    $inactif = Prestataire::factory()->for(User::factory()->prestataire(), 'user')->create(['nom_entreprise' => 'Prestations inactives']);
    Prestation::factory()->inactive()->create(['prestataire_id' => $inactif->id]);

    $reponse = chercher()->assertOk();

    expect(nomsTrouves($reponse))->toBe(['Visible']);
});

it('renvoie les champs attendus calculés sur les prestations actives', function () {
    $p = prestataireRecherche(['nom_entreprise' => 'Garage A', 'quartier' => 'Malika', 'adresse' => 'Rue 1'], 8000);
    Prestation::factory()->create(['prestataire_id' => $p->id, 'prix' => 5000]);
    Prestation::factory()->inactive()->create(['prestataire_id' => $p->id, 'prix' => 1000]);

    chercher()->assertOk()->assertJsonPath('data.0', [
        'id' => $p->id,
        'nomEntreprise' => 'Garage A',
        'metier' => $p->metier->value,
        'quartier' => 'Malika',
        'adresse' => 'Rue 1',
        'prixMin' => 5000,
        'nombrePrestations' => 2,
        'noteMoyenne' => null,
        'nombreAvis' => 0,
    ]);
});

it('ne renvoie aucune donnée personnelle', function () {
    $p = prestataireRecherche();
    $user = $p->user;

    $reponse = chercher()->assertOk();
    $contenu = $reponse->getContent();

    expect(array_keys($reponse->json('data.0')))->toBe(['id', 'nomEntreprise', 'metier', 'quartier', 'adresse', 'prixMin', 'nombrePrestations', 'noteMoyenne', 'nombreAvis'])
        ->and($contenu)->not->toContain($user->telephone)
        ->and($contenu)->not->toContain($user->id)
        ->and($contenu)->not->toContain((string) $user->email)
        ->and($contenu)->not->toContain('user');
});

it('filtre par métier', function () {
    prestataireRecherche(['nom_entreprise' => 'Garage'], 10000, StatutCompte::Actif, Metier::Mecanique);
    prestataireRecherche(['nom_entreprise' => 'Salon'], 10000, StatutCompte::Actif, Metier::Coiffure);

    expect(nomsTrouves(chercher(['metier' => 'coiffure'])))->toBe(['Salon']);
});

it('filtre par quartier sans tenir compte de la casse ni des espaces superflus', function () {
    prestataireRecherche(['nom_entreprise' => 'A', 'quartier' => 'Keur Massar Nord']);
    prestataireRecherche(['nom_entreprise' => 'B', 'quartier' => 'Malika']);

    expect(nomsTrouves(chercher(['quartier' => '  keur   MASSAR nord '])))->toBe(['A']);
});

it('cherche dans le nom d\'entreprise ou le nom d\'une prestation active', function () {
    prestataireRecherche(['nom_entreprise' => 'Garage Moussa']);
    $autre = prestataireRecherche(['nom_entreprise' => 'Chez Awa']);
    Prestation::factory()->create(['prestataire_id' => $autre->id, 'nom' => 'Vidange complète']);
    $inactif = prestataireRecherche(['nom_entreprise' => 'Chez Bob']);
    Prestation::factory()->inactive()->create(['prestataire_id' => $inactif->id, 'nom' => 'Vidange cachée']);

    expect(nomsTrouves(chercher(['q' => 'MOUSSA'])))->toBe(['Garage Moussa'])
        ->and(nomsTrouves(chercher(['q' => 'vidange'])))->toBe(['Chez Awa']);
});

it('traite % et _ comme des caractères ordinaires dans q', function () {
    prestataireRecherche(['nom_entreprise' => 'Promo 100% auto']);
    prestataireRecherche(['nom_entreprise' => 'Garage_Test']);
    prestataireRecherche(['nom_entreprise' => 'Garage Xtest']);
    prestataireRecherche(['nom_entreprise' => 'Autre']);

    expect(nomsTrouves(chercher(['q' => '%'])))->toBe(['Promo 100% auto'])
        ->and(nomsTrouves(chercher(['q' => 'e_t'])))->toBe(['Garage_Test']);
});

it('filtre par fourchette de prix sur une même prestation active', function () {
    prestataireRecherche(['nom_entreprise' => 'Pas cher'], 3000);
    prestataireRecherche(['nom_entreprise' => 'Moyen'], 10000);
    prestataireRecherche(['nom_entreprise' => 'Cher'], 50000);

    $deuxExtremes = prestataireRecherche(['nom_entreprise' => 'Extrêmes'], 3000);
    Prestation::factory()->create(['prestataire_id' => $deuxExtremes->id, 'prix' => 50000]);

    $inactif = prestataireRecherche(['nom_entreprise' => 'Inactif dans la fourchette'], 40000);
    Prestation::factory()->inactive()->create(['prestataire_id' => $inactif->id, 'prix' => 10000]);

    expect(nomsTrouves(chercher(['prix_min' => 5000, 'prix_max' => 20000])))->toBe(['Moyen'])
        ->and(nomsTrouves(chercher(['prix_min' => 40000])))->toBe(['Cher', 'Extrêmes', 'Inactif dans la fourchette'])
        ->and(nomsTrouves(chercher(['prix_max' => 3000])))->toBe(['Extrêmes', 'Pas cher'])
        ->and(nomsTrouves(chercher(['prix_min' => 10000, 'prix_max' => 10000])))->toBe(['Moyen']);
});

it('combine les filtres', function () {
    prestataireRecherche(['nom_entreprise' => 'Garage Malika', 'quartier' => 'Malika'], 10000, StatutCompte::Actif, Metier::Mecanique);
    prestataireRecherche(['nom_entreprise' => 'Garage Jaxaay', 'quartier' => 'Jaxaay'], 10000, StatutCompte::Actif, Metier::Mecanique);
    prestataireRecherche(['nom_entreprise' => 'Salon Malika', 'quartier' => 'Malika'], 10000, StatutCompte::Actif, Metier::Coiffure);
    prestataireRecherche(['nom_entreprise' => 'Garage Malika cher', 'quartier' => 'Malika'], 90000, StatutCompte::Actif, Metier::Mecanique);

    $reponse = chercher(['metier' => 'mecanique', 'quartier' => 'malika', 'q' => 'garage', 'prix_max' => 20000]);

    expect(nomsTrouves($reponse))->toBe(['Garage Malika']);
});

it('trie par nom par défaut, sans tenir compte de la casse', function () {
    prestataireRecherche(['nom_entreprise' => 'charlie']);
    prestataireRecherche(['nom_entreprise' => 'Alpha']);
    prestataireRecherche(['nom_entreprise' => 'Bravo']);

    expect(nomsTrouves(chercher()))->toBe(['Alpha', 'Bravo', 'charlie'])
        ->and(nomsTrouves(chercher(['tri' => 'nom'])))->toBe(['Alpha', 'Bravo', 'charlie']);
});

it('trie par prix minimal croissant et décroissant', function () {
    prestataireRecherche(['nom_entreprise' => 'Milieu'], 10000);
    prestataireRecherche(['nom_entreprise' => 'Bas'], 2000);
    prestataireRecherche(['nom_entreprise' => 'Haut'], 30000);
    $deux = prestataireRecherche(['nom_entreprise' => 'Deux prix'], 25000);
    Prestation::factory()->create(['prestataire_id' => $deux->id, 'prix' => 1000]);

    expect(nomsTrouves(chercher(['tri' => 'prix_asc'])))->toBe(['Deux prix', 'Bas', 'Milieu', 'Haut'])
        ->and(nomsTrouves(chercher(['tri' => 'prix_desc'])))->toBe(['Haut', 'Milieu', 'Bas', 'Deux prix']);
});

it('pagine par 20', function () {
    foreach (range(1, 25) as $i) {
        prestataireRecherche(['nom_entreprise' => sprintf('Prestataire %02d', $i)]);
    }

    $page1 = chercher()->assertOk()->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.per_page', 20)->assertJsonPath('meta.total', 25);
    $page2 = chercher(['page' => 2])->assertOk()->assertJsonCount(5, 'data');

    expect(nomsTrouves($page1)[0])->toBe('Prestataire 01')
        ->and(nomsTrouves($page2)[0])->toBe('Prestataire 21');
});

it('refuse des paramètres invalides avec des messages en français', function (array $params, string $champ) {
    chercher($params)->assertUnprocessable()->assertJsonValidationErrors($champ);
})->with([
    'métier inconnu' => [['metier' => 'plomberie'], 'metier'],
    'tri inconnu' => [['tri' => 'hasard'], 'tri'],
    'prix_min non numérique' => [['prix_min' => 'abc'], 'prix_min'],
    'prix_min négatif' => [['prix_min' => -1], 'prix_min'],
    'prix_max trop de décimales' => [['prix_max' => '10.999'], 'prix_max'],
    'prix_min > prix_max' => [['prix_min' => 20000, 'prix_max' => 10000], 'prix_max'],
    'q trop long' => [['q' => str_repeat('a', 101)], 'q'],
    'page invalide' => [['page' => 0], 'page'],
]);

it('renvoie un message d\'erreur en français pour une fourchette incohérente', function () {
    chercher(['prix_min' => 20000, 'prix_max' => 10000])
        ->assertUnprocessable()
        ->assertJsonPath('errors.prix_max.0', 'Le prix maximum doit être supérieur ou égal au prix minimum.');
});
