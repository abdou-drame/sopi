import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/reservation/modeles.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_reservation.dart';

final _reservation = ReservationCreee(
  id: 'r-1',
  statut: 'en_attente',
  debut: DateTime.utc(2026, 10, 24, 10),
  prestationNom: 'Coupe femme',
  prix: 5000,
  prestataireNom: 'Salon Fatou',
);

Future<void> _ouvrir(
  WidgetTester tester, {
  ReservationCreee? reservation,
}) async {
  await lancerAcces(
    tester,
    AdaptateurSimule(routesClient()),
    jeton: 'jeton',
    chemin: Chemins.accueil,
  );
  GoRouter.of(
    tester.element(find.byType(Scaffold).first),
  ).go(Chemins.demandeEnvoyee('r-1'), extra: reservation);
  await passerTransition(tester);
}

void main() {
  setUpAll(initialiserFormats);

  group('Demande envoyée', () {
    testWidgets('affiche le récapitulatif de la demande', (tester) async {
      await _ouvrir(tester, reservation: _reservation);
      expect(find.text('Demande'), findsOneWidget);
      expect(find.text('envoyée'), findsOneWidget);
      expect(
        find.text(
          'Salon Fatou va répondre à votre demande. Aucun paiement maintenant.',
        ),
        findsOneWidget,
      );
      expect(find.text('Service'), findsOneWidget);
      expect(find.text('Coupe femme'), findsOneWidget);
      expect(find.text(formaterDate(_reservation.debut)), findsOneWidget);
      expect(find.text(formaterArgent(5000)), findsOneWidget);
      expect(
        find.textContaining('vous disposez d\'un délai limité pour payer'),
        findsOneWidget,
      );
      expect(find.text('Voir ma réservation'), findsOneWidget);
      expect(find.text("Retour à l'accueil"), findsOneWidget);
    });

    testWidgets('« Voir ma réservation » ouvre le détail', (tester) async {
      await _ouvrir(tester, reservation: _reservation);
      await toucher(tester, find.text('Voir ma réservation'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.detailReservation('r-1'));
    });

    testWidgets('« Retour à l\'accueil » ramène à l\'accueil', (tester) async {
      await _ouvrir(tester, reservation: _reservation);
      await toucher(tester, find.text("Retour à l'accueil"));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.accueil);
    });

    testWidgets('ouvert sans données : retour à l\'accueil', (tester) async {
      await _ouvrir(tester);
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.accueil);
    });

    testWidgets('petit écran 320×568 : défilement, pas de débordement', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 568);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await _ouvrir(tester, reservation: _reservation);
      expect(tester.takeException(), isNull);
      expect(find.text('Voir ma réservation'), findsOneWidget);
    });
  });
}
