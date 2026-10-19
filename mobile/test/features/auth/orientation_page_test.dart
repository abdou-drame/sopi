import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/auth/session.dart';

import '../../aide_api.dart';
import 'aide_acces.dart';

AdaptateurSimule _serveur() => AdaptateurSimule({
  'GET /metiers': const Simulee(200, <Object>[]),
  'POST /auth/deconnexion': const Simulee(200, {}),
  'GET /me': const Simulee(200, {
    'id': '1',
    'role': 'client',
    'nom': 'Diallo',
    'prenom': 'A',
  }),
});

void main() {
  group('Orientation', () {
    testWidgets('sans session : s\'affiche en premier', (tester) async {
      await lancerAcces(tester, _serveur(), depuisOrientation: true);
      expect(cheminCourant(tester), Chemins.orientation);
      expect(find.text('Je cherche un artisan'), findsOneWidget);
      expect(find.text('Je suis artisan'), findsOneWidget);
      expect(find.text('Keur Massar'), findsOneWidget);
      expect(find.text('Se connecter'), findsOneWidget);
      expect(find.text('Commencer'), findsNWidgets(2));
    });

    testWidgets('« Je cherche un artisan » ouvre l\'inscription client', (
      tester,
    ) async {
      await lancerAcces(tester, _serveur(), depuisOrientation: true);
      await toucher(tester, find.text('Je cherche un artisan'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.inscription);
    });

    testWidgets('« Je suis artisan » ouvre l\'inscription prestataire', (
      tester,
    ) async {
      await lancerAcces(tester, _serveur(), depuisOrientation: true);
      await toucher(tester, find.text('Je suis artisan'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.inscriptionPrestataire);
    });

    testWidgets(
      '« Se connecter » ouvre la connexion, avec son mot de passe oublié',
      (tester) async {
        await lancerAcces(tester, _serveur(), depuisOrientation: true);
        await toucher(tester, find.text('Se connecter'));
        await passerTransition(tester);
        expect(cheminCourant(tester), Chemins.connexion);
        expect(find.text('Mot de passe oublié ?'), findsOneWidget);
      },
    );

    testWidgets('session restaurée : l\'orientation est sautée', (
      tester,
    ) async {
      await lancerAcces(tester, _serveur(), jeton: 'abc');
      expect(cheminCourant(tester), Chemins.accueil);
      expect(find.text('Je suis artisan'), findsNothing);
    });

    testWidgets('boutons accessibles, d\'au moins 44 px', (tester) async {
      final poignee = tester.ensureSemantics();
      await lancerAcces(tester, _serveur(), depuisOrientation: true);
      for (final titre in ['Je cherche un artisan', 'Je suis artisan']) {
        final carte = find.bySemanticsLabel(RegExp('^$titre[.]'));
        expect(carte, findsOneWidget);
        final taille = tester.getSize(carte);
        expect(taille.width, greaterThanOrEqualTo(44));
        expect(taille.height, greaterThanOrEqualTo(44));
      }
      poignee.dispose();
    });

    testWidgets('320 × 568 : pas de débordement, le contenu défile', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 568);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await lancerAcces(tester, _serveur(), depuisOrientation: true);
      expect(tester.takeException(), isNull);
      await toucher(tester, find.text('Se connecter'));
      expect(cheminCourant(tester), Chemins.connexion);
    });

    testWidgets('animations réduites : tout est visible tout de suite', (
      tester,
    ) async {
      await lancerAcces(tester, _serveur(), depuisOrientation: true);
      expect(find.byType(Opacity), findsNothing);
    });

    testWidgets('animations actives : entrée en fondu de 400 ms au plus', (
      tester,
    ) async {
      await lancerAcces(
        tester,
        _serveur(),
        depuisOrientation: true,
        sansAnimation: false,
      );
      await tester.pump(const Duration(milliseconds: 450));
      final opacites = tester.widgetList<Opacity>(find.byType(Opacity));
      expect(opacites, isNotEmpty);
      expect(opacites.every((o) => o.opacity == 1), isTrue);
    });
  });

  testWidgets('déconnexion : retour sur l\'orientation', (tester) async {
    final c = await lancerAcces(tester, _serveur(), jeton: 'abc');
    expect(cheminCourant(tester), Chemins.accueil);
    await tester.runAsync(() => c.read(sessionProvider.notifier).deconnecter());
    await passerTransition(tester);
    expect(cheminCourant(tester), Chemins.orientation);
  });
}
