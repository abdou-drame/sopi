import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/animation/chargement.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/reservation/widgets/photo_prestataire.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_reservation.dart';

const _urlPhoto = '/api/v1/prestataires/p-1/photos/ph-1';

Map<String, Simulee> _routes({
  Map<String, Object?>? profil,
  Map<String, Object?>? avis,
}) => routesClient({
  'GET /prestataires/p-1': Simulee(200, profil ?? profilApi()),
  'GET /prestataires/p-1/avis': Simulee(
    200,
    avis ??
        avisApi([
          unAvis('a-1', 'Awa D.', 5, 'Accueil chaleureux et travail soigné.'),
          unAvis('a-2', 'Moussa K.', 4, null),
          unAvis('a-3', 'Fanta S.', 5, 'Parfait.'),
          unAvis('a-4', 'Ibou T.', 3, 'Quatrième avis.'),
        ]),
  ),
});

Future<void> _ouvrir(
  WidgetTester tester,
  AdaptateurSimule serveur, {
  String chemin = '/prestataires/p-1',
}) async {
  await lancerAcces(tester, serveur, jeton: 'jeton', chemin: chemin);
  await passerTransition(tester);
}

void main() {
  setUpAll(initialiserFormats);

  group('Fiche prestataire', () {
    testWidgets('chargement : squelettes gris puis contenu', (tester) async {
      final serveur = AdaptateurLentPour(_routes(), {'GET /prestataires/p-1'});
      await lancerAcces(
        tester,
        serveur,
        jeton: 'jeton',
        chemin: '/prestataires/p-1',
      );
      await avancer(tester, 100);
      expect(find.byType(Squelette), findsWidgets);
      expect(find.text('Services'), findsNothing);
      serveur.liberer();
      await passerTransition(tester);
      expect(find.text('Salon Fatou'), findsOneWidget);
    });

    testWidgets('données : en-tête, chiffres, services, avis', (tester) async {
      await _ouvrir(tester, AdaptateurSimule(_routes()));
      expect(find.text('Salon Fatou'), findsOneWidget);
      expect(find.text('Malika · Coiffure'), findsOneWidget);
      expect(find.text('4,9'), findsOneWidget);
      expect(find.text('128'), findsOneWidget);
      expect(find.text('Services'), findsOneWidget);
      expect(find.text('Coupe femme'), findsOneWidget);
      expect(find.text('Durée : 1 h'), findsOneWidget);
      expect(find.text('Durée : 45 min'), findsOneWidget);
      expect(find.text(formaterArgent(5000)), findsOneWidget);
      expect(find.text('Acompte ${formaterArgent(1500)}'), findsOneWidget);
      expect(find.text('Avis récents'), findsOneWidget);
      expect(find.text('Awa D.'), findsOneWidget);
      expect(find.text('Fanta S.'), findsOneWidget);
      expect(find.text('Ibou T.'), findsNothing);
      expect(find.text('Tout voir'), findsOneWidget);
    });

    testWidgets('sans service choisi, le bouton est inactif', (tester) async {
      await _ouvrir(tester, AdaptateurSimule(_routes()));
      expect(find.text('Choisissez un service'), findsOneWidget);
      await toucher(tester, find.text('Choisir un créneau'));
      await avancer(tester, 500);
      expect(cheminCourant(tester), Chemins.fichePrestataire('p-1'));
    });

    testWidgets('choisir un service met à jour la barre et ouvre le créneau', (
      tester,
    ) async {
      await _ouvrir(tester, AdaptateurSimule(_routes()));
      await toucher(tester, find.text('Brushing'));
      await avancer(tester, 400);
      expect(find.text('Choisissez un service'), findsNothing);
      expect(find.text(formaterArgent(3000)), findsNWidgets(2));
      await toucher(tester, find.text('Choisir un créneau'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.creneau('p-1'));
    });

    testWidgets('photos : galerie chargée avec l\'URL complétée', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(
        _routes(
          profil: profilApi(
            photos: [
              {'id': 'ph-1', 'url': _urlPhoto},
            ],
          ),
        ),
      );
      await _ouvrir(tester, serveur);
      expect(find.byType(PhotoReseau), findsOneWidget);
      final requete = serveur.requetes.where(
        (r) => r.path.endsWith('/photos/ph-1'),
      );
      expect(requete, isNotEmpty);
      expect(requete.first.path, startsWith('http'));
    });

    testWidgets('aucun avis : message', (tester) async {
      await _ouvrir(tester, AdaptateurSimule(_routes(avis: avisApi([]))));
      expect(find.text('Aucun avis pour le moment.'), findsOneWidget);
      expect(find.text('Tout voir'), findsNothing);
    });

    testWidgets('prestataire introuvable : erreur et « Réessayer »', (
      tester,
    ) async {
      final routes = _routes();
      routes['GET /prestataires/p-1'] = const Simulee(404, {
        'message': 'Prestataire introuvable.',
      });
      await _ouvrir(tester, AdaptateurSimule(routes));
      expect(find.text('Prestataire introuvable.'), findsOneWidget);
      expect(find.text('Réessayer'), findsOneWidget);
    });

    testWidgets('« Tout voir » ouvre la liste paginée des avis', (
      tester,
    ) async {
      final routes = _routes();
      final serveur = AdaptateurSimule(routes);
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Tout voir'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.avisPrestataire('p-1'));
      expect(find.text('Ibou T.'), findsOneWidget);
      expect(find.text('Quatrième avis.'), findsOneWidget);
      // Le nom de l'entreprise est transmis par la fiche pour le titre « Avis sur … ».
      expect(find.text('Avis sur'), findsOneWidget);
      expect(find.text('Salon Fatou'), findsOneWidget);
      expect(find.byKey(const ValueKey('synthese-avis')), findsOneWidget);
    });

    testWidgets('petit écran 320×568 : pas de débordement', (tester) async {
      tester.view.physicalSize = const Size(320, 568);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await _ouvrir(tester, AdaptateurSimule(_routes()));
      expect(tester.takeException(), isNull);
    });
  });

  group('Tous les avis', () {
    testWidgets('pagination : la page 2 s\'ajoute', (tester) async {
      // Vue haute : tous les avis et le bouton « Voir plus d'avis » tiennent à l'écran.
      tester.view.physicalSize = const Size(390, 2400);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final routes = _routes(
        avis: avisApi([
          for (var i = 1; i <= 8; i++) unAvis('a-$i', 'Client $i', 4, 'Bien'),
        ], derniere: 2),
      );
      final serveur = AdaptateurSimule(routes);
      await _ouvrir(tester, serveur, chemin: Chemins.avisPrestataire('p-1'));
      expect(find.text('Client 1'), findsOneWidget);
      routes['GET /prestataires/p-1/avis'] = Simulee(
        200,
        avisApi([unAvis('a-9', 'Client 9', 5, 'Top')], page: 2, derniere: 2),
      );
      await tester.tap(find.text("Voir plus d'avis"));
      await avancer(tester, 300);
      expect(find.text('Client 9'), findsOneWidget);
      expect(find.text("Voir plus d'avis"), findsNothing);
    });

    testWidgets('lien direct sans nom : titre générique', (tester) async {
      await _ouvrir(
        tester,
        AdaptateurSimule(_routes()),
        chemin: Chemins.avisPrestataire('p-1'),
      );
      expect(find.text('ce prestataire'), findsOneWidget);
    });

    testWidgets('vide et erreur', (tester) async {
      await _ouvrir(
        tester,
        AdaptateurSimule(_routes(avis: avisApi([]))),
        chemin: Chemins.avisPrestataire('p-1'),
      );
      expect(find.text('Aucun avis pour le moment.'), findsOneWidget);
    });
  });
}
