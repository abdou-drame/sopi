import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_reservation.dart';

Map<String, Simulee> _routes(int nonLues) => routesClient({
  'GET /reservations': const Simulee(200, {
    'data': [],
    'meta': {'current_page': 1, 'last_page': 1, 'per_page': 20, 'total': 0},
  }),
  'GET /prestataires': Simulee(
    200,
    pagePrestataires([prestataireListe('p-1', 'Salon Fatou', note: 4.9)]),
  ),
  'GET /notifications/non-lues/compteur': Simulee(200, {'nonLues': nonLues}),
});

final _pastille = find.byKey(const ValueKey('pastille-notifications'));

void main() {
  setUpAll(initialiserFormats);

  group('Accueil : barre d\'onglets, cloche et menu', () {
    testWidgets('la barre propose Accueil, Rechercher, Réservations, Profil', (
      tester,
    ) async {
      await lancerAcces(tester, AdaptateurSimule(_routes(0)), jeton: 'jeton');
      await passerTransition(tester);
      for (final libelle in ['Rechercher', 'Réservations', 'Profil']) {
        expect(find.bySemanticsLabel(libelle), findsWidgets);
      }
      expect(find.text('Accueil'), findsWidgets);
      expect(find.bySemanticsLabel('Notifications'), findsOneWidget);
    });

    testWidgets('« Rechercher » ouvre la liste sans filtre de métier', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(_routes(0));
      await lancerAcces(tester, serveur, jeton: 'jeton');
      await passerTransition(tester);
      await tester.tap(find.bySemanticsLabel('Rechercher').last);
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.rechercher);
      expect(find.text('Prestataires'), findsOneWidget);
      expect(find.text('Salon Fatou'), findsWidgets);
      final liste = serveur.requetes.where((r) => r.path == '/prestataires');
      expect(liste.last.queryParameters.containsKey('metier'), isFalse);
    });

    testWidgets(
      'la cloche affiche la pastille quand des notifications attendent',
      (tester) async {
        await lancerAcces(tester, AdaptateurSimule(_routes(3)), jeton: 'jeton');
        await passerTransition(tester);
        expect(_pastille, findsOneWidget);
      },
    );

    testWidgets('pas de pastille sans notification non lue', (tester) async {
      await lancerAcces(tester, AdaptateurSimule(_routes(0)), jeton: 'jeton');
      await passerTransition(tester);
      expect(_pastille, findsNothing);
    });

    testWidgets('le compteur est relu au retour au premier plan', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(_routes(0));
      await lancerAcces(tester, serveur, jeton: 'jeton');
      await passerTransition(tester);
      int appels() => serveur.requetes
          .where((r) => r.path == '/notifications/non-lues/compteur')
          .length;
      final avant = appels();
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await passerTransition(tester);
      expect(appels(), greaterThan(avant));
    });

    testWidgets('la cloche ouvre les notifications', (tester) async {
      await lancerAcces(tester, AdaptateurSimule(_routes(1)), jeton: 'jeton');
      await passerTransition(tester);
      await toucher(tester, find.bySemanticsLabel('Notifications'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.notifications);
    });

    testWidgets('« Menu » ouvre l\'onglet Profil', (tester) async {
      await lancerAcces(tester, AdaptateurSimule(_routes(0)), jeton: 'jeton');
      await passerTransition(tester);
      await toucher(tester, find.bySemanticsLabel('Menu'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.profil);
    });
  });
}
