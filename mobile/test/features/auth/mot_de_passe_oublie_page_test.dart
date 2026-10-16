import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/auth/mot_de_passe_oublie_page.dart';

import '../../aide_api.dart';
import 'aide_acces.dart';

const _messageApi =
    "Si un compte correspond à cette adresse, un lien de réinitialisation vient d'être envoyé. "
    'Pensez à vérifier vos spams.';

const _route = 'POST /auth/mot-de-passe-oublie';

Finder _bouton(String libelle) => find.widgetWithText(FilledButton, libelle);

Future<AdaptateurSimule> _lancer(
  WidgetTester tester,
  Map<String, Simulee> routes, {
  AdaptateurSimule? serveur,
  String supportEmail = '',
  List<Uri>? urls,
}) async {
  final s = serveur ?? AdaptateurSimule(routes);
  await lancerAcces(
    tester,
    s,
    chemin: Chemins.motDePasseOublie,
    supportEmail: supportEmail,
    urlsOuvertes: urls,
  );
  return s;
}

Future<void> _envoyer(
  WidgetTester tester, [
  String email = 'a@exemple.sn',
]) async {
  await saisir(tester, 0, email);
  await toucher(tester, _bouton('Envoyer le lien'));
}

void main() {
  group('Mot de passe oublié', () {
    testWidgets('affiche les éléments de la maquette', (tester) async {
      await _lancer(tester, {});
      expect(find.textContaining('Mot de passe'), findsOneWidget);
      expect(find.textContaining('oublié ?'), findsOneWidget);
      expect(
        find.text(
          "Saisissez l'adresse e-mail de votre compte. Nous vous envoyons un lien valable 60 minutes.",
        ),
        findsOneWidget,
      );
      expect(find.text('E-mail'), findsOneWidget);
      expect(_bouton('Envoyer le lien'), findsOneWidget);
      expect(find.text(messageSupportSansEmail), findsOneWidget);
    });

    testWidgets('e-mail vide ou invalide : erreur locale, aucune requête', (
      tester,
    ) async {
      final serveur = await _lancer(tester, {});
      await toucher(tester, _bouton('Envoyer le lien'));
      expect(find.text("L'adresse e-mail est obligatoire."), findsOneWidget);
      await saisir(tester, 0, 'pas-un-mail');
      await toucher(tester, _bouton('Envoyer le lien'));
      expect(find.text("L'adresse e-mail n'est pas valide."), findsOneWidget);
      expect(serveur.requetes.where((r) => r.method == 'POST'), isEmpty);
    });

    testWidgets('chargement : indicateur puis état « lien envoyé »', (
      tester,
    ) async {
      final serveur = AdaptateurLent({
        _route: const Simulee(200, {'message': _messageApi}),
      });
      await _lancer(tester, {}, serveur: serveur);
      await _envoyer(tester);
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      serveur.liberer();
      await avancer(tester, 200);
      expect(find.byType(CircularProgressIndicator), findsNothing);
      expect(find.text(_messageApi), findsOneWidget);
    });

    testWidgets('succès : message du serveur tel quel, ligne explicative', (
      tester,
    ) async {
      final serveur = await _lancer(tester, {
        _route: const Simulee(200, {'message': 'Message du serveur.'}),
      });
      await _envoyer(tester, ' a@exemple.sn ');
      expect(serveur.requetes.firstWhere((r) => r.method == 'POST').data, {
        'email': 'a@exemple.sn',
      });
      expect(find.text('Message du serveur.'), findsOneWidget);
      expect(find.text(messageLienOuvreNavigateur), findsOneWidget);
    });

    testWidgets('renvoi : bloqué 30 s avec décompte, puis libéré', (
      tester,
    ) async {
      await _lancer(tester, {
        _route: const Simulee(200, {'message': _messageApi}),
      });
      await _envoyer(tester);
      expect(_bouton('Renvoyer dans 30 s'), findsOneWidget);
      expect(find.textContaining('Renvoyer dans'), findsOneWidget);
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
      );
      await tester.pump(const Duration(seconds: 10));
      expect(find.textContaining('Renvoyer dans 2'), findsOneWidget);
      await tester.pump(const Duration(seconds: 30));
      await tester.pump();
      expect(_bouton('Renvoyer le lien'), findsOneWidget);
      expect(
        tester.widget<FilledButton>(_bouton('Renvoyer le lien')).onPressed,
        isNotNull,
      );
    });

    testWidgets('429 : message avec délai puis bouton libéré', (tester) async {
      await _lancer(tester, {
        _route: const Simulee(
          429,
          {'message': 'Trop de demandes.'},
          {'retry-after': '3'},
        ),
      });
      await _envoyer(tester);
      expect(
        find.text('Trop de tentatives. Réessayez dans 00:03.'),
        findsOneWidget,
      );
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
      );
      await tester.pump(const Duration(seconds: 3));
      await tester.pump();
      expect(find.textContaining('Réessayez dans'), findsNothing);
      expect(
        tester.widget<FilledButton>(_bouton('Envoyer le lien')).onPressed,
        isNotNull,
      );
    });

    testWidgets('422 : message sous le champ e-mail', (tester) async {
      await _lancer(tester, {
        _route: const Simulee(422, {
          'message': 'Données invalides.',
          'errors': {
            'email': ['Adresse refusée.'],
          },
        }),
      });
      await _envoyer(tester);
      expect(find.text('Adresse refusée.'), findsOneWidget);
    });

    testWidgets('réseau coupé : message de connexion', (tester) async {
      final serveur = AdaptateurSimule({})..reseauCoupe = true;
      await _lancer(tester, {}, serveur: serveur);
      await _envoyer(tester);
      expect(
        find.text('Pas de connexion. Vérifiez votre réseau.'),
        findsOneWidget,
      );
    });

    testWidgets('support non configuré : texte simple, rien ne s\'ouvre', (
      tester,
    ) async {
      final urls = <Uri>[];
      await _lancer(tester, {}, urls: urls);
      await toucher(tester, find.text(messageSupportSansEmail));
      expect(urls, isEmpty);
    });

    testWidgets('support configuré : ouvre la messagerie', (tester) async {
      final urls = <Uri>[];
      await _lancer(
        tester,
        {},
        supportEmail: 'support@exemple.test',
        urls: urls,
      );
      await toucher(tester, find.text(messageSupportSansEmail));
      expect(urls.single.toString(), 'mailto:support@exemple.test');
    });

    testWidgets('petit écran avec clavier : défile sans débordement', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 480);
      tester.view.devicePixelRatio = 1;
      tester.view.viewInsets = const FakeViewPadding(bottom: 200);
      addTearDown(tester.view.reset);
      await _lancer(tester, {});
      expect(tester.takeException(), isNull);
      await saisir(tester, 0, 'abc');
      expect(tester.takeException(), isNull);
    });
  });
}
