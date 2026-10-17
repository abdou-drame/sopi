import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/reservation/depot_reservation.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_reservation.dart';

final _maintenant = DateTime.utc(2026, 10, 23, 8, 0);
final _debut = DateTime.utc(2026, 10, 24, 10);

/// Le décompte écrit « 1 h 52 » avec des espaces insécables.
const _unHeure52 = '1 h 52';

Map<String, Object?> _reservation({
  String statut = 'acceptee',
  DateTime? paiementAvant,
  DateTime? reponseAvant,
}) => {
  ...reservationApi(
    'r-1',
    debut: _debut,
    statut: statut,
    peutAnnuler: true,
    peutPayer: statut == 'acceptee',
  ),
  if (paiementAvant != null) 'paiementAvant': paiementAvant.toIso8601String(),
  if (reponseAvant != null) 'reponseAvant': reponseAvant.toIso8601String(),
};

Future<void> _ouvrir(
  WidgetTester tester,
  Map<String, Object?> reservation, {
  required String chemin,
}) async {
  tester.view.physicalSize = const Size(390, 1200);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  await lancerAcces(
    tester,
    AdaptateurSimule(
      routesClient({
        'GET /reservations/r-1': Simulee(200, {'data': reservation}),
        'GET /prestataires/p-1': Simulee(200, profilApi()),
      }),
    ),
    jeton: 'jeton',
    chemin: chemin,
    surcharges: [horlogeProvider.overrideWithValue(() => _maintenant)],
  );
  await passerTransition(tester);
}

void main() {
  setUpAll(initialiserFormats);

  group('Compte à rebours de paiement (paiementAvant)', () {
    testWidgets('présent : la pastille décompte le temps restant', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        _reservation(
          paiementAvant: _maintenant.add(const Duration(hours: 1, minutes: 52)),
        ),
        chemin: Chemins.detailReservation('r-1'),
      );
      expect(find.text('Il reste'), findsOneWidget);
      expect(find.text(_unHeure52), findsOneWidget);
      expect(find.textContaining('Vous avez un délai limité'), findsNothing);
    });

    testWidgets(
      'absent : pas de pastille, rappel du délai limité sans chiffre',
      (tester) async {
        await _ouvrir(
          tester,
          _reservation(),
          chemin: Chemins.detailReservation('r-1'),
        );
        expect(find.textContaining('Il reste'), findsNothing);
        expect(
          find.textContaining('Vous avez un délai limité'),
          findsOneWidget,
        );
      },
    );

    testWidgets('à zéro : « Délai dépassé »', (tester) async {
      await _ouvrir(
        tester,
        _reservation(paiementAvant: _maintenant),
        chemin: Chemins.detailReservation('r-1'),
      );
      await avancer(tester, 2000);
      expect(find.text('Délai dépassé'), findsOneWidget);
      expect(find.text('Il reste'), findsNothing);
    });

    testWidgets('page de paiement : décompte présent puis absent', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        _reservation(
          paiementAvant: _maintenant.add(const Duration(hours: 1, minutes: 52)),
        ),
        chemin: Chemins.paiement('r-1'),
      );
      expect(find.text('Il reste'), findsOneWidget);
      expect(find.text(_unHeure52), findsOneWidget);
    });

    testWidgets('page de paiement sans échéance : pas de décompte', (
      tester,
    ) async {
      await _ouvrir(tester, _reservation(), chemin: Chemins.paiement('r-1'));
      expect(find.textContaining('Il reste'), findsNothing);
    });
  });

  group('Délai de réponse du prestataire (reponseAvant)', () {
    testWidgets('présent : la date limite de l API est affichée', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        _reservation(statut: 'en_attente', reponseAvant: _debut),
        chemin: Chemins.detailReservation('r-1'),
      );
      expect(
        find.textContaining(
          "Le prestataire a jusqu'au sam. 24 oct., 10h00 pour répondre.",
        ),
        findsOneWidget,
      );
      expect(find.textContaining('délai limité pour répondre'), findsNothing);
    });

    testWidgets('absent : texte sans chiffre', (tester) async {
      await _ouvrir(
        tester,
        _reservation(statut: 'en_attente'),
        chemin: Chemins.detailReservation('r-1'),
      );
      expect(find.textContaining('délai limité pour répondre'), findsOneWidget);
      expect(find.textContaining("Le prestataire a jusqu'au"), findsNothing);
    });
  });

  group('Logos de paiement', () {
    testWidgets('Wave et Orange Money sont affichés sur le récapitulatif', (
      tester,
    ) async {
      await _ouvrir(tester, _reservation(), chemin: Chemins.paiement('r-1'));
      final assets = tester
          .widgetList<Image>(find.byType(Image))
          .map((i) => (i.image as AssetImage).assetName)
          .toList();
      expect(
        assets,
        containsAll(['assets/wave.png', 'assets/orange_money.png']),
      );
      expect(tester.takeException(), isNull);
    });
  });
}
