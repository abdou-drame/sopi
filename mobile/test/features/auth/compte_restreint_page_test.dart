import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/auth/compte_restreint_page.dart';

import '../../aide_api.dart';
import 'aide_acces.dart';

/// Ouvre l'application, se connecte et reçoit le 403 donné : l'écran « compte restreint » s'affiche.
Future<List<Uri>> _arriver(
  WidgetTester tester,
  Map<String, Object?> corps403, {
  String supportEmail = '',
}) async {
  final urls = <Uri>[];
  await lancerAcces(
    tester,
    AdaptateurSimule({
      'POST /auth/connexion': Simulee(403, corps403),
      'POST /auth/deconnexion': const Simulee(204, null),
    }),
    supportEmail: supportEmail,
    urlsOuvertes: urls,
  );
  await saisir(tester, 0, 'awa@exemple.sn');
  await saisir(tester, 1, 'secret');
  await toucher(tester, find.widgetWithText(FilledButton, 'Se connecter'));
  await passerTransition(tester);
  return urls;
}

void main() {
  setUpAll(initialiserFormats);

  group('Compte restreint', () {
    testWidgets('suspendu avec motif et date de fin', (tester) async {
      await _arriver(tester, {
        'message': 'Votre compte est suspendu. Contactez le support.',
        'motif': 'Plusieurs absences non justifiées signalées par des clients.',
        'finSuspension': '2026-10-30T10:00:00Z',
      });
      expect(cheminCourant(tester), Chemins.compteRestreint);
      expect(find.textContaining('Compte'), findsWidgets);
      expect(find.textContaining('suspendu'), findsWidgets);
      expect(
        find.text(
          'Votre accès est temporairement limité suite à une sanction.',
        ),
        findsOneWidget,
      );
      expect(find.text('Motif'), findsOneWidget);
      expect(
        find.text(
          'Plusieurs absences non justifiées signalées par des clients.',
        ),
        findsOneWidget,
      );
      expect(find.text("Jusqu'au"), findsOneWidget);
      expect(find.text('30 oct. 2026'), findsOneWidget);
    });

    testWidgets('sans date de fin : la ligne « Jusqu\'au » est masquée', (
      tester,
    ) async {
      await _arriver(tester, {
        'message': 'Votre compte est suspendu.',
        'motif': 'Retards répétés',
      });
      expect(find.text('Retards répétés'), findsOneWidget);
      expect(find.text("Jusqu'au"), findsNothing);
    });

    testWidgets('sans motif : le message du serveur est affiché', (
      tester,
    ) async {
      await _arriver(tester, {'message': 'Votre compte a été banni.'});
      expect(find.text('Information'), findsOneWidget);
      expect(find.text('Votre compte a été banni.'), findsOneWidget);
      expect(find.textContaining('banni'), findsWidgets);
      expect(find.text('Votre accès à Sopi a été retiré.'), findsOneWidget);
    });

    testWidgets('refusé : titre et phrase adaptés', (tester) async {
      await _arriver(tester, {
        'message': "Votre demande d'inscription a été refusée.",
        'motif': 'Pièce illisible',
      });
      expect(find.textContaining('refusé'), findsWidgets);
      expect(
        find.text("Votre demande d'inscription n'a pas été acceptée."),
        findsOneWidget,
      );
    });

    testWidgets('support non configuré : pas de bouton de contact', (
      tester,
    ) async {
      await _arriver(tester, {'message': 'Votre compte est suspendu.'});
      expect(find.text('Contacter le support'), findsNothing);
      expect(find.text('Se déconnecter'), findsOneWidget);
    });

    testWidgets('support configuré : le bouton ouvre la messagerie', (
      tester,
    ) async {
      final urls = await _arriver(tester, {
        'message': 'Votre compte est suspendu.',
      }, supportEmail: 'support@exemple.test');
      await toucher(tester, find.text('Contacter le support'));
      expect(urls.single.toString(), 'mailto:support@exemple.test');
    });

    testWidgets('« Se déconnecter » revient à la connexion', (tester) async {
      await _arriver(tester, {'message': 'Votre compte est suspendu.'});
      await toucher(tester, find.text('Se déconnecter'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.connexion);
      expect(find.text('Heureux de'), findsNothing);
      expect(find.textContaining('vous revoir'), findsOneWidget);
    });

    testWidgets('petit écran : défile sans débordement', (tester) async {
      tester.view.physicalSize = const Size(320, 480);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await _arriver(tester, {
        'message': 'Votre compte est suspendu.',
        'motif': 'Retards répétés',
        'finSuspension': '2026-10-30T10:00:00Z',
      });
      expect(tester.takeException(), isNull);
    });

    for (final cas in [
      (
        'suspendu',
        'Votre accès est temporairement limité suite à une sanction.',
      ),
      ('refuse', "Votre demande d'inscription n'a pas été acceptée."),
      ('banni', 'Votre accès à Sopi a été retiré.'),
      (
        'en_attente',
        "Votre compte est en attente de validation par l'équipe Sopi.",
      ),
    ]) {
      testWidgets('statutCompte ${cas.$1} : phrase propre au statut', (
        tester,
      ) async {
        // Le message est neutre : seul `statutCompte` peut déterminer le type affiché.
        await _arriver(tester, {
          'message': 'Accès refusé.',
          'statutCompte': cas.$1,
        });
        expect(find.text(cas.$2), findsOneWidget);
      });
    }

    test('TypeRestriction se déduit du message du serveur', () {
      expect(
        TypeRestriction.depuisMessage('Votre compte est suspendu.'),
        TypeRestriction.suspendu,
      );
      expect(
        TypeRestriction.depuisMessage('Votre compte a été banni.'),
        TypeRestriction.banni,
      );
      expect(
        TypeRestriction.depuisMessage("Votre demande a été refusée."),
        TypeRestriction.refuse,
      );
      expect(TypeRestriction.depuisMessage(''), TypeRestriction.suspendu);
    });
  });
}
