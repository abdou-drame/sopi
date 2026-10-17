import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sopi/core/api/stockage_jeton.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/core/routing/routeur.dart';
import 'package:sopi/core/theme/theme_sopi.dart';
import 'package:sopi/features/auth/session.dart';
import 'package:sopi/features/auth/utilisateur.dart';

import '../aide_api.dart';

const _client = Utilisateur(id: '1', role: RoleSopi.client);
const _prestataire = Utilisateur(id: '2', role: RoleSopi.prestataire);

void main() {
  setUpAll(initialiserFormats);

  group('redirectionSopi', () {
    test('session inconnue : écran de démarrage', () {
      expect(
        redirectionSopi(const SessionInconnue(), Chemins.accueil),
        Chemins.demarrage,
      );
      expect(
        redirectionSopi(const SessionInconnue(), Chemins.demarrage),
        isNull,
      );
    });

    test(
      'non connecté : routes publiques libres, le reste renvoie à la connexion',
      () {
        const s = NonConnecte();
        expect(redirectionSopi(s, Chemins.connexion), isNull);
        expect(redirectionSopi(s, Chemins.inscription), isNull);
        expect(redirectionSopi(s, Chemins.motDePasseOublie), isNull);
        expect(redirectionSopi(s, Chemins.accueil), Chemins.connexion);
        expect(redirectionSopi(s, Chemins.tableau), Chemins.connexion);
        expect(
          redirectionSopi(s, Chemins.detailReservation('abc')),
          Chemins.connexion,
        );
        expect(redirectionSopi(s, Chemins.demarrage), Chemins.connexion);
      },
    );

    test('client : espace client, jamais l\'espace prestataire', () {
      const s = Connecte(_client);
      expect(redirectionSopi(s, Chemins.connexion), Chemins.accueil);
      expect(redirectionSopi(s, Chemins.demarrage), Chemins.accueil);
      expect(redirectionSopi(s, Chemins.reservations), isNull);
      expect(redirectionSopi(s, Chemins.fichePrestataire('p1')), isNull);
      expect(redirectionSopi(s, Chemins.tableau), Chemins.accueil);
      expect(redirectionSopi(s, Chemins.retrait), Chemins.accueil);
    });

    test('prestataire : espace prestataire, jamais l\'espace client', () {
      const s = Connecte(_prestataire);
      expect(redirectionSopi(s, Chemins.connexion), Chemins.tableau);
      expect(redirectionSopi(s, Chemins.agenda), isNull);
      expect(redirectionSopi(s, Chemins.demarrerPrestation('r1')), isNull);
      expect(redirectionSopi(s, Chemins.accueil), Chemins.tableau);
      expect(redirectionSopi(s, Chemins.reservations), Chemins.tableau);
    });

    test('compte restreint : tout renvoie à l\'écran dédié', () {
      const s = Restreint(message: 'Compte suspendu.');
      expect(redirectionSopi(s, Chemins.connexion), Chemins.compteRestreint);
      expect(redirectionSopi(s, Chemins.accueil), Chemins.compteRestreint);
      expect(redirectionSopi(s, Chemins.compteRestreint), isNull);
    });

    test('l\'écran de diagnostic reste accessible connecté', () {
      expect(redirectionSopi(const Connecte(_client), Chemins.etatApi), isNull);
    });
  });

  group('routeur et coques', () {
    Future<(WidgetTester, ProviderContainer)> lancer(
      WidgetTester tester,
      AdaptateurSimule serveur, {
      String? jeton,
    }) async {
      final conteneur = ProviderContainer(
        overrides: surchargesApi(serveur, StockageJetonMemoire(jeton)),
      );
      addTearDown(conteneur.dispose);
      await tester.pumpWidget(
        UncontrolledProviderScope(
          container: conteneur,
          child: Consumer(
            builder: (context, ref, _) => MaterialApp.router(
              theme: ThemeSopi.construire(policeGoogle: false),
              routerConfig: ref.watch(routeurProvider),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      return (tester, conteneur);
    }

    String chemin(WidgetTester tester) =>
        GoRouter.of(tester.element(find.byType(Scaffold).first)).state.uri.path;

    testWidgets('sans jeton : écran de connexion', (tester) async {
      await lancer(tester, AdaptateurSimule({}));
      expect(find.text('Se connecter'), findsOneWidget);
      expect(chemin(tester), Chemins.connexion);
    });

    testWidgets('client : coque Accueil / Rechercher / Réservations / Profil', (
      tester,
    ) async {
      final serveur = AdaptateurSimule({
        'GET /me': const Simulee(200, {'id': '1', 'role': 'client'}),
      });
      await lancer(tester, serveur, jeton: 'abc');
      expect(chemin(tester), Chemins.accueil);
      expect(find.text('Accueil'), findsWidgets);

      await tester.tap(find.bySemanticsLabel('Rechercher').last);
      await tester.pumpAndSettle();
      expect(chemin(tester), Chemins.rechercher);

      await tester.tap(find.bySemanticsLabel('Réservations'));
      await tester.pumpAndSettle();
      expect(chemin(tester), Chemins.reservations);

      await tester.tap(find.bySemanticsLabel('Profil'));
      await tester.pumpAndSettle();
      expect(chemin(tester), Chemins.profil);
    });

    testWidgets(
      'prestataire : coque Accueil / Agenda / Portefeuille / Profil',
      (tester) async {
        final serveur = AdaptateurSimule({
          'GET /me': const Simulee(200, {'id': '2', 'role': 'prestataire'}),
        });
        await lancer(tester, serveur, jeton: 'abc');
        expect(chemin(tester), Chemins.tableau);

        await tester.tap(find.bySemanticsLabel('Agenda'));
        await tester.pumpAndSettle();
        expect(chemin(tester), Chemins.agenda);

        await tester.tap(find.bySemanticsLabel('Portefeuille'));
        await tester.pumpAndSettle();
        expect(chemin(tester), Chemins.portefeuille);
      },
    );

    testWidgets('la déconnexion ramène à la connexion', (tester) async {
      final serveur = AdaptateurSimule({
        'GET /me': const Simulee(200, {'id': '1', 'role': 'client'}),
        'POST /auth/deconnexion': const Simulee(200, {}),
      });
      await lancer(tester, serveur, jeton: 'abc');
      await tester.tap(find.bySemanticsLabel('Profil'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Se déconnecter'));
      await tester.pumpAndSettle();
      // Confirmation demandée avant la déconnexion.
      await tester.tap(find.text('Se déconnecter').last);
      await tester.pumpAndSettle();
      expect(chemin(tester), Chemins.connexion);
    });

    testWidgets('un 403 à la connexion affiche l\'écran « compte restreint »', (
      tester,
    ) async {
      final serveur = AdaptateurSimule({
        'POST /auth/connexion': const Simulee(403, {
          'message': 'Votre compte est suspendu.',
          'motif': 'Retards répétés',
        }),
      });
      await lancer(tester, serveur);
      await tester.enterText(find.byType(TextField).first, 'awa@exemple.sn');
      await tester.enterText(find.byType(TextField).last, 'secret');
      await tester.ensureVisible(find.text('Se connecter'));
      await tester.pump();
      await tester.tap(find.text('Se connecter'));
      await tester.pumpAndSettle();
      expect(chemin(tester), Chemins.compteRestreint);
      expect(find.textContaining('suspendu'), findsOneWidget);
      expect(find.text('Motif'), findsOneWidget);
      expect(find.text('Retards répétés'), findsOneWidget);
    });
  });
}
