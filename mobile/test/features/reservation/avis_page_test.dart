import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_reservation.dart';

final _debut = DateTime.utc(2026, 10, 24, 10);

Map<String, Object?> _terminee({Map<String, Object?>? avis}) => reservationApi(
  'r-1',
  debut: _debut,
  statut: 'terminee',
  peutAnnuler: false,
  peutNoter: avis == null,
  avis: avis,
);

AdaptateurSimule _serveur({Map<String, Simulee> routes = const {}}) =>
    AdaptateurSimule(
      routesClient({
        'GET /reservations/r-1': Simulee(200, {'data': _terminee()}),
        'POST /reservations/r-1/avis': const Simulee(201, {
          'data': {'id': 'a-1', 'note': 5},
        }),
        ...routes,
      }),
    );

Future<void> _ouvrir(WidgetTester tester, AdaptateurSimule serveur) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: Chemins.avis('r-1'),
  );
  await passerTransition(tester);
}

List<dynamic> _envois(AdaptateurSimule s) =>
    s.requetes.where((r) => r.method == 'POST').map((r) => r.data).toList();

void main() {
  setUpAll(initialiserFormats);

  testWidgets(
    'affiche le prestataire, la prestation et la phrase de publication',
    (tester) async {
      await _ouvrir(tester, _serveur());
      expect(find.text('Salon Fatou'), findsOneWidget);
      expect(find.text('Coupe femme · 24 oct.'), findsOneWidget);
      expect(find.text('Publier mon avis'), findsOneWidget);
      expect(
        find.text(
          'Votre avis est visible immédiatement sur la fiche du prestataire.',
        ),
        findsOneWidget,
      );
      expect(find.textContaining('modéré'), findsNothing);
    },
  );

  testWidgets('chaque étoile affiche son libellé', (tester) async {
    await _ouvrir(tester, _serveur());
    const libelles = {
      1: 'Très mauvais',
      2: 'Mauvais',
      3: 'Correct',
      4: 'Bien',
      5: 'Très bien',
    };
    for (final e in libelles.entries) {
      await tester.tap(find.byKey(ValueKey('etoile-${e.key}')));
      await tester.pump();
      expect(find.text(e.value), findsOneWidget);
    }
  });

  testWidgets('sans note : refus local et aucun envoi', (tester) async {
    final serveur = _serveur();
    await _ouvrir(tester, serveur);
    await tester.tap(find.text('Publier mon avis'));
    await tester.pump();
    expect(find.text('Choisissez une note de 1 à 5.'), findsOneWidget);
    expect(_envois(serveur), isEmpty);
  });

  testWidgets('commentaire trop court : refus local', (tester) async {
    final serveur = _serveur();
    await _ouvrir(tester, serveur);
    await tester.tap(find.byKey(const ValueKey('etoile-4')));
    await tester.enterText(find.byType(TextField), 'ok');
    await tester.tap(find.text('Publier mon avis'));
    await tester.pump();
    expect(find.textContaining('au moins 3 caractères'), findsOneWidget);
    expect(_envois(serveur), isEmpty);
  });

  testWidgets('envoie la note et le commentaire, puis remercie', (
    tester,
  ) async {
    final serveur = _serveur();
    await _ouvrir(tester, serveur);
    await tester.tap(find.byKey(const ValueKey('etoile-5')));
    await tester.enterText(find.byType(TextField), 'Accueil chaleureux.');
    await tester.tap(find.text('Publier mon avis'));
    await tester.pumpAndSettle();
    expect(_envois(serveur), [
      {'note': 5, 'commentaire': 'Accueil chaleureux.'},
    ]);
    expect(find.text('Merci ! Votre avis a été publié.'), findsOneWidget);
    expect(find.text('Publier mon avis'), findsNothing);
  });

  testWidgets('sans commentaire : le champ n\'est pas envoyé', (tester) async {
    final serveur = _serveur();
    await _ouvrir(tester, serveur);
    await tester.tap(find.byKey(const ValueKey('etoile-3')));
    await tester.tap(find.text('Publier mon avis'));
    await tester.pumpAndSettle();
    expect(_envois(serveur), [
      {'note': 3},
    ]);
  });

  testWidgets(
    '409 : le message du serveur est affiché, on reste sur l\'écran',
    (tester) async {
      final serveur = _serveur(
        routes: {
          'POST /reservations/r-1/avis': const Simulee(409, {
            'message': 'Vous avez déjà donné votre avis sur cette réservation.',
          }),
        },
      );
      await _ouvrir(tester, serveur);
      await tester.tap(find.byKey(const ValueKey('etoile-5')));
      await tester.tap(find.text('Publier mon avis'));
      await tester.pumpAndSettle();
      expect(
        find.text('Vous avez déjà donné votre avis sur cette réservation.'),
        findsOneWidget,
      );
      expect(find.text('Publier mon avis'), findsOneWidget);
    },
  );

  testWidgets('422 : l\'erreur s\'affiche sous le commentaire', (tester) async {
    final serveur = _serveur(
      routes: {
        'POST /reservations/r-1/avis': const Simulee(422, {
          'message': 'Données invalides.',
          'errors': {
            'commentaire': ['Le commentaire contient des termes interdits.'],
          },
        }),
      },
    );
    await _ouvrir(tester, serveur);
    await tester.tap(find.byKey(const ValueKey('etoile-2')));
    await tester.enterText(find.byType(TextField), 'Commentaire valide');
    await tester.tap(find.text('Publier mon avis'));
    await tester.pumpAndSettle();
    expect(
      find.text('Le commentaire contient des termes interdits.'),
      findsOneWidget,
    );
  });

  testWidgets('429 : message de patience', (tester) async {
    final serveur = _serveur(
      routes: {
        'POST /reservations/r-1/avis': const Simulee(
          429,
          {'message': 'Trop de tentatives.'},
          {'Retry-After': '30'},
        ),
      },
    );
    await _ouvrir(tester, serveur);
    await tester.tap(find.byKey(const ValueKey('etoile-5')));
    await tester.tap(find.text('Publier mon avis'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Trop de tentatives'), findsOneWidget);
  });
}
