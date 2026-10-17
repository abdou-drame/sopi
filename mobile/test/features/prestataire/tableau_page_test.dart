import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/animation/chargement.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';
import 'aide_prestataire.dart';

Map<String, Simulee> _routes({
  List<Map<String, Object?>>? demandes,
  int? total,
  num solde = 42500,
  int nonLues = 0,
}) {
  final liste = demandes ?? [demandeApi()];
  return {
    'GET /me': Simulee(200, utilisateurPrestataire()),
    'GET /prestataire/solde': Simulee(200, {'solde': solde}),
    'GET /prestataire/reservations': Simulee(
      200,
      pageDemandesApi(liste, total: total),
    ),
    'GET /notifications/non-lues/compteur': Simulee(200, {'nonLues': nonLues}),
  };
}

void _vue390(WidgetTester tester) {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

void main() {
  setUpAll(initialiserFormats);

  group('Tableau prestataire', () {
    testWidgets('salutation, solde, compteur et nouvelles demandes', (
      tester,
    ) async {
      _vue390(tester);
      final serveur = AdaptateurSimule(
        _routes(
          demandes: [
            demandeApi(),
            demandeApi(
              id: 'r-2',
              prenom: 'Khadija',
              initiale: 'S',
              prestation: 'Tresses',
              prix: 12000,
              debut: '2026-10-24T14:00:00+00:00',
            ),
          ],
          total: 3,
        ),
      );
      await lancerAcces(tester, serveur, jeton: 'jeton');
      await passerTransition(tester);

      expect(cheminCourant(tester), Chemins.tableau);
      expect(find.textContaining('Fatou !'), findsOneWidget);
      expect(find.text('F'), findsOneWidget);
      expect(find.text('Solde disponible'), findsOneWidget);
      expect(find.textContaining('42 500', findRichText: true), findsOneWidget);
      expect(find.text('Demandes à traiter'), findsOneWidget);
      expect(find.byKey(const ValueKey('compteur-a-traiter')), findsOneWidget);
      expect(find.text('3'), findsOneWidget);
      expect(find.text('Nouvelles demandes'), findsOneWidget);
      expect(find.text('Aminata D.'), findsOneWidget);
      expect(find.text('Sam. 24 oct., 10h00'), findsOneWidget);
      expect(find.text('Coupe femme · 5 000 FCFA'), findsOneWidget);
      expect(find.text('Khadija S.'), findsOneWidget);
      expect(find.text('Accepter'), findsNWidgets(2));
      expect(find.text('Refuser'), findsNWidgets(2));
      final liste = serveur.requetes.firstWhere(
        (r) => r.path == '/prestataire/reservations',
      );
      expect(liste.queryParameters['statut'], 'en_attente');
    });

    testWidgets('le bouton Retirer est inactif', (tester) async {
      _vue390(tester);
      await lancerAcces(tester, AdaptateurSimule(_routes()), jeton: 'jeton');
      await passerTransition(tester);
      await tester.tap(find.byKey(const ValueKey('bouton-retirer')));
      await avancer(tester, 100);
      expect(cheminCourant(tester), Chemins.tableau);
    });

    testWidgets('la cloche affiche un point s\'il y a des non lues', (
      tester,
    ) async {
      _vue390(tester);
      await lancerAcces(
        tester,
        AdaptateurSimule(_routes(nonLues: 2)),
        jeton: 'jeton',
      );
      await passerTransition(tester);
      expect(
        find.byKey(const ValueKey('pastille-notifications')),
        findsOneWidget,
      );
    });

    testWidgets('sans notification non lue, pas de point', (tester) async {
      _vue390(tester);
      await lancerAcces(tester, AdaptateurSimule(_routes()), jeton: 'jeton');
      await passerTransition(tester);
      expect(
        find.byKey(const ValueKey('pastille-notifications')),
        findsNothing,
      );
    });

    testWidgets('la cloche ouvre la page Notifications', (tester) async {
      _vue390(tester);
      await lancerAcces(tester, AdaptateurSimule(_routes()), jeton: 'jeton');
      await passerTransition(tester);
      await tester.tap(find.bySemanticsLabel('Notifications'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.notifications);
    });

    testWidgets('aucune demande : message et compteur à 0', (tester) async {
      _vue390(tester);
      await lancerAcces(
        tester,
        AdaptateurSimule(_routes(demandes: [], total: 0)),
        jeton: 'jeton',
      );
      await passerTransition(tester);
      expect(
        find.text('Aucune demande à traiter pour le moment.'),
        findsOneWidget,
      );
      expect(find.text('0'), findsOneWidget);
    });

    testWidgets('chargement : squelettes, puis contenu', (tester) async {
      _vue390(tester);
      final serveur = AdaptateurLentPour(_routes(), {
        'GET /prestataire/reservations',
        'GET /prestataire/solde',
      });
      await lancerAcces(tester, serveur, jeton: 'jeton');
      await avancer(tester, 100);
      expect(find.byType(Squelette), findsWidgets);
      expect(find.text('Aminata D.'), findsNothing);
      serveur.liberer();
      await passerTransition(tester);
      expect(find.text('Aminata D.'), findsOneWidget);
    });

    testWidgets('erreur : message du serveur et bouton Réessayer', (
      tester,
    ) async {
      _vue390(tester);
      final routes = _routes()
        ..['GET /prestataire/reservations'] = const Simulee(500, {
          'message': 'Erreur du serveur.',
        });
      await lancerAcces(tester, AdaptateurSimule(routes), jeton: 'jeton');
      await passerTransition(tester);
      expect(find.text('Réessayer'), findsOneWidget);
      expect(find.text('Aminata D.'), findsNothing);
      // La salutation reste affichée.
      expect(find.textContaining('Fatou !'), findsOneWidget);
    });

    testWidgets('Tout voir ouvre la liste des réservations', (tester) async {
      _vue390(tester);
      await lancerAcces(tester, AdaptateurSimule(_routes()), jeton: 'jeton');
      await passerTransition(tester);
      await tester.tap(find.text('Tout voir'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.reservationsPrestataire);
      expect(find.bySemanticsLabel('À traiter'), findsOneWidget);
    });

    testWidgets('Accepter appelle l\'API puis relit le tableau', (
      tester,
    ) async {
      _vue390(tester);
      final routes = _routes()
        ..['POST /prestataire/reservations/r-1/accepter'] = Simulee(
          200,
          demandeApi(statut: 'acceptee', avecMontants: false),
        );
      final serveur = AdaptateurSimule(routes);
      await lancerAcces(tester, serveur, jeton: 'jeton');
      await passerTransition(tester);
      await tester.tap(find.text('Accepter'));
      await passerTransition(tester);
      expect(
        serveur.requetes.where(
          (r) => r.method == 'POST' && r.path.endsWith('/accepter'),
        ),
        hasLength(1),
      );
      final lectures = serveur.requetes.where(
        (r) => r.method == 'GET' && r.path == '/prestataire/reservations',
      );
      expect(lectures.length, greaterThanOrEqualTo(2));
    });
  });
}
