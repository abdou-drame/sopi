import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_prestataire.dart';

void main() {
  setUpAll(initialiserFormats);

  Future<AdaptateurSimule> ouvrir(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final serveur = AdaptateurSimule({
      'GET /me': Simulee(200, utilisateurPrestataire()),
      'GET /prestataire/solde': const Simulee(200, {'solde': 0}),
      'GET /prestataire/reservations': Simulee(200, pageDemandesApi([])),
      'GET /notifications/non-lues/compteur': const Simulee(200, {
        'nonLues': 0,
      }),
      'GET /prestataire/prestations': const Simulee(200, {'data': []}),
      'GET /prestataire/agenda': const Simulee(200, {'data': []}),
    });
    await lancerAcces(
      tester,
      serveur,
      jeton: 'jeton',
      chemin: Chemins.profilPrestataire,
    );
    await passerTransition(tester);
    return serveur;
  }

  testWidgets('Profil → Mes services', (tester) async {
    await ouvrir(tester);
    await tester.tap(find.text('Mes services'));
    await passerTransition(tester);
    expect(cheminCourant(tester), Chemins.services);
  });

  testWidgets('Profil → Mes disponibilités', (tester) async {
    await ouvrir(tester);
    await tester.tap(find.text('Mes disponibilités'));
    await passerTransition(tester);
    expect(cheminCourant(tester), Chemins.disponibilites);
    expect(find.text('Aucun créneau'), findsOneWidget);
  });
}
