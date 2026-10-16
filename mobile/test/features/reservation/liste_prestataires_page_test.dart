import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/animation/chargement.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_reservation.dart';
import 'package:sopi/features/reservation/widgets/feuilles_filtres.dart';

Map<String, Simulee> _routes({
  List<Map<String, Object?>>? elements,
  Map<String, Object?>? page,
}) => routesClient({
  'GET /prestataires': Simulee(
    200,
    page ??
        pagePrestataires(
          elements ??
              [
                prestataireListe('p-1', 'Salon Fatou', note: 4.9),
                prestataireListe('p-2', 'Coiffure Awa', note: null, avis: 0),
              ],
        ),
  ),
  'GET /prestataires/p-1': Simulee(200, profilApi()),
});

Future<void> _ouvrir(
  WidgetTester tester,
  AdaptateurSimule serveur, {
  String chemin = '/prestataires?metier=coiffure',
}) async {
  await lancerAcces(tester, serveur, jeton: 'jeton', chemin: chemin);
  await passerTransition(tester);
}

Map<String, dynamic> _derniere(AdaptateurSimule s) =>
    s.requetes.lastWhere((r) => r.path == '/prestataires').queryParameters;

int _appels(AdaptateurSimule s) =>
    s.requetes.where((r) => r.path == '/prestataires').length;

void main() {
  setUpAll(initialiserFormats);

  group('Liste des prestataires', () {
    testWidgets('chargement : squelettes gris puis cartes', (tester) async {
      final serveur = AdaptateurLentPour(_routes(), {'GET /prestataires'});
      await lancerAcces(
        tester,
        serveur,
        jeton: 'jeton',
        chemin: '/prestataires?metier=coiffure',
      );
      await avancer(tester, 100);
      expect(find.byType(Squelette), findsWidgets);
      serveur.liberer();
      await passerTransition(tester);
      expect(find.text('Salon Fatou'), findsOneWidget);
    });

    testWidgets('données : titre métier, compteur, carte et filtre métier', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur);
      expect(find.text('Coiffure'), findsWidgets);
      expect(find.text('2 prestataires disponibles'), findsOneWidget);
      expect(find.text('Salon Fatou'), findsOneWidget);
      expect(find.text('4,9'), findsOneWidget);
      expect(find.text('Nouveau'), findsOneWidget);
      expect(find.text('Réserver'), findsNWidgets(2));
      expect(_derniere(serveur)['metier'], 'coiffure');
    });

    testWidgets('sans filtre : titre « Prestataires », singulier', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(
        _routes(elements: [prestataireListe('p-1', 'Salon Fatou')]),
      );
      await _ouvrir(tester, serveur, chemin: Chemins.prestataires);
      expect(find.text('Prestataires'), findsOneWidget);
      expect(find.text('1 prestataire disponible'), findsOneWidget);
      expect(_derniere(serveur).containsKey('metier'), isFalse);
    });

    testWidgets('recherche : un seul appel après 400 ms', (tester) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur);
      final avant = _appels(serveur);
      await tester.enterText(find.byType(TextField), 'fat');
      await tester.enterText(find.byType(TextField), 'fatou');
      await avancer(tester, 200);
      expect(_appels(serveur), avant);
      await avancer(tester, 300);
      await passerTransition(tester);
      expect(_appels(serveur), avant + 1);
      expect(_derniere(serveur)['q'], 'fatou');
    });

    testWidgets('filtre quartier : pilule active, puis effacer', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Quartier'));
      await passerTransition(tester);
      await toucher(tester, find.widgetWithText(LigneChoix, 'Malika'));
      await toucher(tester, find.text('Appliquer'));
      await passerTransition(tester);
      expect(_derniere(serveur)['quartier'], 'Malika');
      expect(find.text('Quartier : Malika'), findsOneWidget);
      await toucher(tester, find.text('Quartier : Malika'));
      await passerTransition(tester);
      await toucher(tester, find.text('Effacer'));
      await passerTransition(tester);
      expect(_derniere(serveur).containsKey('quartier'), isFalse);
      expect(find.text('Quartier'), findsOneWidget);
    });

    testWidgets('filtre note : note_min envoyé', (tester) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Note'));
      await passerTransition(tester);
      await toucher(tester, find.text('4,5 et plus'));
      await toucher(tester, find.text('Appliquer'));
      await passerTransition(tester);
      expect(_derniere(serveur)['note_min'].toString(), '4.5');
    });

    testWidgets('filtre prix : maximum inférieur au minimum refusé', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Prix'));
      await passerTransition(tester);
      await tester.enterText(find.byType(TextField).at(1), '5000');
      await tester.enterText(find.byType(TextField).at(2), '2000');
      await toucher(tester, find.text('Appliquer'));
      await avancer(tester, 200);
      expect(
        find.text(
          'Le prix maximum doit être supérieur ou égal au prix minimum.',
        ),
        findsOneWidget,
      );
      await tester.enterText(find.byType(TextField).at(2), '8000');
      await toucher(tester, find.text('Appliquer'));
      await passerTransition(tester);
      expect(_derniere(serveur)['prix_min'].toString(), '5000');
      expect(_derniere(serveur)['prix_max'].toString(), '8000');
    });

    testWidgets('tri : feuille « Trier par »', (tester) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur);
      await toucher(tester, find.byIcon(Icons.filter_list_rounded));
      await passerTransition(tester);
      await toucher(tester, find.text('Prix décroissant'));
      await passerTransition(tester);
      expect(_derniere(serveur)['tri'], 'prix_desc');
    });

    testWidgets('pagination : la page 2 est ajoutée en bas de liste', (
      tester,
    ) async {
      final routes = _routes(
        page: pagePrestataires(
          [for (var i = 1; i <= 8; i++) prestataireListe('p-$i', 'Presta $i')],
          derniere: 2,
          total: 9,
        ),
      );
      final serveur = AdaptateurSimule(routes);
      await _ouvrir(tester, serveur);
      routes['GET /prestataires'] = Simulee(
        200,
        pagePrestataires(
          [prestataireListe('p-9', 'Presta 9')],
          page: 2,
          derniere: 2,
          total: 9,
        ),
      );
      await tester.drag(find.byType(CustomScrollView), const Offset(0, -3000));
      await passerTransition(tester);
      expect(_derniere(serveur)['page'].toString(), '2');
      expect(find.text('Presta 9'), findsOneWidget);
    });

    testWidgets('liste vide : message et « Effacer les filtres »', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(_routes(elements: []));
      await _ouvrir(tester, serveur);
      expect(find.text('Aucun prestataire ne correspond.'), findsOneWidget);
      expect(find.text('Effacer les filtres'), findsNothing);
      await tester.enterText(find.byType(TextField), 'zzz');
      await avancer(tester, 500);
      await passerTransition(tester);
      expect(find.text('Effacer les filtres'), findsOneWidget);
      await toucher(tester, find.text('Effacer les filtres'));
      await passerTransition(tester);
      expect(_derniere(serveur).containsKey('q'), isFalse);
    });

    testWidgets('erreur serveur puis réessai', (tester) async {
      final routes = _routes();
      routes['GET /prestataires'] = const Simulee(500, {'message': 'Panne.'});
      final serveur = AdaptateurSimule(routes);
      await _ouvrir(tester, serveur);
      expect(find.text('Réessayer'), findsOneWidget);
      routes['GET /prestataires'] = Simulee(
        200,
        pagePrestataires([prestataireListe('p-1', 'Salon Fatou')]),
      );
      await toucher(tester, find.text('Réessayer'));
      await passerTransition(tester);
      expect(find.text('Salon Fatou'), findsOneWidget);
    });

    testWidgets('toucher une carte ouvre la fiche', (tester) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Salon Fatou'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.fichePrestataire('p-1'));
    });

    testWidgets('petit écran 320×568 : pas de débordement', (tester) async {
      tester.view.physicalSize = const Size(320, 568);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await _ouvrir(tester, AdaptateurSimule(_routes()));
      expect(tester.takeException(), isNull);
    });
  });
}
