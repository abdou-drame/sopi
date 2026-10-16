import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/animation/chargement.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_reservation.dart';

DateTime demain10h() {
  final n = DateTime.now().toUtc();
  return DateTime.utc(n.year, n.month, n.day + 1, 10);
}

Map<String, Simulee> _routes({
  Map<String, Object?>? prochaine,
  List<Map<String, Object?>>? decouvrir,
}) => routesClient({
  'GET /reservations': Simulee(200, {
    'data': [?prochaine],
    'meta': {'current_page': 1, 'last_page': 1, 'per_page': 20, 'total': 1},
  }),
  'GET /prestataires': Simulee(
    200,
    pagePrestataires(
      decouvrir ??
          [
            prestataireListe('p-1', 'Salon Fatou', note: 4.9),
            prestataireListe(
              'p-2',
              'Garage Keur Massar',
              metier: 'mecanique',
              quartier: 'Jaxaay',
              note: 4.8,
            ),
            prestataireListe(
              'p-3',
              'Pressing Express',
              metier: 'pressing',
              note: null,
              avis: 0,
            ),
            prestataireListe('p-4', 'Quatrième', note: 4.0),
          ],
    ),
  ),
  'GET /prestataires/p-1': Simulee(200, profilApi()),
});

void main() {
  setUpAll(initialiserFormats);

  group('Accueil client', () {
    testWidgets('chargement : squelettes gris, puis contenu', (tester) async {
      final serveur = AdaptateurLentPour(_routes(), {'GET /prestataires'});
      await lancerAcces(tester, serveur, jeton: 'jeton');
      await avancer(tester, 100);
      expect(find.byType(Squelette), findsWidgets);
      expect(find.text('Salon Fatou'), findsNothing);
      serveur.liberer();
      await passerTransition(tester);
      expect(find.textContaining('Bonjour'), findsOneWidget);
    });

    testWidgets('données : salutation, métiers, 3 prestataires', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(_routes());
      await lancerAcces(tester, serveur, jeton: 'jeton');
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.accueil);
      expect(find.textContaining('Aminata !'), findsOneWidget);
      expect(find.text('A'), findsOneWidget);
      expect(find.text('Mécanique'), findsOneWidget);
      expect(find.text('Coiffure'), findsOneWidget);
      expect(find.text('Pressing'), findsOneWidget);
      expect(find.text('Prestataires à découvrir'), findsOneWidget);
      expect(find.text('Salon Fatou'), findsOneWidget);
      expect(find.text('Garage Keur Massar'), findsOneWidget);
      expect(find.text('Pressing Express'), findsOneWidget);
      expect(find.text('Quatrième'), findsNothing);
      expect(find.text('Coiffure · Malika'), findsOneWidget);
      expect(find.text('4,9'), findsOneWidget);
      expect(find.text('Nouveau'), findsOneWidget);
      final tri = serveur.requetes.firstWhere((r) => r.path == '/prestataires');
      expect(tri.queryParameters['tri'], 'note');
      expect(find.text('Prochaine réservation'), findsNothing);
    });

    testWidgets('prochaine réservation : « Demain, 10h00 » et « Voir »', (
      tester,
    ) async {
      await lancerAcces(
        tester,
        AdaptateurSimule(
          _routes(prochaine: reservationApi('r-1', debut: demain10h())),
        ),
        jeton: 'jeton',
      );
      await passerTransition(tester);
      expect(find.text('Prochaine réservation'), findsOneWidget);
      expect(find.text('Demain, 10h00'), findsOneWidget);
      expect(find.text('Coiffure · Salon Fatou'), findsOneWidget);
      await toucher(tester, find.text('Voir'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.detailReservation('r-1'));
    });

    testWidgets('toucher un métier ouvre la liste filtrée', (tester) async {
      await lancerAcces(tester, AdaptateurSimule(_routes()), jeton: 'jeton');
      await passerTransition(tester);
      await toucher(tester, find.text('Coiffure'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.prestataires);
    });

    testWidgets('« Tout voir » ouvre la liste des prestataires', (
      tester,
    ) async {
      await lancerAcces(tester, AdaptateurSimule(_routes()), jeton: 'jeton');
      await passerTransition(tester);
      await toucher(tester, find.text('Tout voir'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.prestataires);
    });

    testWidgets('erreur serveur sur l\'accueil, puis réessai réussi', (
      tester,
    ) async {
      final routes = _routes();
      routes['GET /metiers'] = const Simulee(500, {'message': 'Panne.'});
      final serveur = AdaptateurSimule(routes);
      await lancerAcces(tester, serveur, jeton: 'jeton');
      await passerTransition(tester);
      expect(find.text('Réessayer'), findsOneWidget);
      routes['GET /metiers'] = const Simulee(200, metiersApi);
      await toucher(tester, find.text('Réessayer'));
      await passerTransition(tester);
      expect(find.text('Réessayer'), findsNothing);
      expect(find.textContaining('Aminata !'), findsOneWidget);
    });

    testWidgets('petit écran : pas de débordement', (tester) async {
      tester.view.physicalSize = const Size(320, 568);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await lancerAcces(
        tester,
        AdaptateurSimule(
          _routes(prochaine: reservationApi('r-1', debut: demain10h())),
        ),
        jeton: 'jeton',
      );
      await passerTransition(tester);
      expect(tester.takeException(), isNull);
    });
  });
}
