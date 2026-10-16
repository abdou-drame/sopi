import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/animation/apparition.dart';
import 'package:sopi/core/animation/chargement.dart';
import 'package:sopi/core/animation/chiffres.dart';
import 'package:sopi/core/format/formateurs.dart';

import '../aide.dart';

void main() {
  group('delaiCascade', () {
    test('décale de 70 ms par élément et plafonne à 12 éléments', () {
      expect(delaiCascade(0), Duration.zero);
      expect(delaiCascade(3), const Duration(milliseconds: 210));
      expect(delaiCascade(11), const Duration(milliseconds: 770));
      expect(delaiCascade(12), delaiCascade(11));
      expect(delaiCascade(500), delaiCascade(11));
    });
  });

  group('Apparition', () {
    testWidgets('part invisible puis devient visible à la fin', (tester) async {
      await tester.pumpWidget(enrober(const Apparition(child: Text('Bonjour'))));
      expect(opacite(tester, find.text('Bonjour')), 0);

      await tester.pump(const Duration(milliseconds: 150));
      final milieu = opacite(tester, find.text('Bonjour'));
      expect(milieu, greaterThan(0));
      expect(milieu, lessThan(1));

      await tester.pumpAndSettle();
      expect(find.text('Bonjour'), findsOneWidget);
      expect(opacite(tester, find.text('Bonjour')), 1);
    });

    testWidgets('sans animation (accessibilité), le contenu est visible tout de suite', (tester) async {
      await tester.pumpWidget(enrober(const Apparition(child: Text('Bonjour')), sansAnimation: true));
      expect(find.text('Bonjour'), findsOneWidget);
      expect(find.byType(Opacity), findsNothing);
    });

    testWidgets("ne rejoue pas l'animation à une reconstruction", (tester) async {
      Widget app(String texte) => enrober(Apparition(child: Text(texte)));
      await tester.pumpWidget(app('A'));
      await tester.pumpAndSettle();

      await tester.pumpWidget(app('B'));
      expect(opacite(tester, find.text('B')), 1);
    });
  });

  group('ApparitionEnCascade', () {
    testWidgets('fait apparaître les éléments les uns après les autres', (tester) async {
      await tester.pumpWidget(
        enrober(const ApparitionEnCascade(children: [Text('un'), Text('deux'), Text('trois')])),
      );
      await tester.pump(const Duration(milliseconds: 100));
      expect(opacite(tester, find.text('un')), greaterThan(opacite(tester, find.text('trois'))));

      await tester.pumpAndSettle();
      for (final t in ['un', 'deux', 'trois']) {
        expect(opacite(tester, find.text(t)), 1);
      }
    });

    testWidgets('au-delà de 12 éléments, les suivants arrivent ensemble et tout est visible en moins de 1,2 s', (tester) async {
      await tester.pumpWidget(
        enrober(
          SingleChildScrollView(
            child: ApparitionEnCascade(children: [for (var i = 0; i < 40; i++) Text('ligne $i')]),
          ),
        ),
      );
      // 11 × 70 ms + 300 ms = 1,07 s au plus.
      await tester.pump(const Duration(milliseconds: 1100));
      for (final i in [0, 11, 12, 39]) {
        expect(opacite(tester, find.text('ligne $i', skipOffstage: false)), 1, reason: 'ligne $i');
      }
    });

    testWidgets('avec disableAnimations, tout est présent tout de suite', (tester) async {
      await tester.pumpWidget(
        enrober(const ApparitionEnCascade(children: [Text('un'), Text('deux')]), sansAnimation: true),
      );
      expect(find.text('un'), findsOneWidget);
      expect(find.text('deux'), findsOneWidget);
      expect(find.byType(Opacity), findsNothing);
    });

    testWidgets('en ligne, les tuiles arrivent de gauche à droite', (tester) async {
      await tester.pumpWidget(
        enrober(
          const ApparitionEnCascade(axe: Axis.horizontal, children: [Text('a'), Text('b'), Text('c')]),
        ),
      );
      await tester.pump(const Duration(milliseconds: 120));
      expect(opacite(tester, find.text('a')), greaterThan(opacite(tester, find.text('c'))));
      await tester.pumpAndSettle();
      expect(opacite(tester, find.text('c')), 1);
    });
  });

  group('SqueletteVersContenu', () {
    testWidgets('montre le squelette puis le contenu', (tester) async {
      Widget app(bool pret) => enrober(
        SqueletteVersContenu(pret: pret, squelette: const Squelette(key: Key('sq')), contenu: const Text('Données')),
      );
      await tester.pumpWidget(app(false));
      expect(find.byKey(const Key('sq')), findsOneWidget);
      expect(find.text('Données'), findsNothing);

      await tester.pumpWidget(app(true));
      await tester.pumpAndSettle();
      expect(find.text('Données'), findsOneWidget);
      expect(find.byKey(const Key('sq')), findsNothing);
    });
  });

  group('CompteurAnime', () {
    testWidgets('monte de 0 à la valeur en 500 ms', (tester) async {
      await tester.pumpWidget(enrober(const CompteurAnime(valeur: 12000)));
      expect(find.text(formaterArgent(0)), findsOneWidget);

      await tester.pump(const Duration(milliseconds: 250));
      final milieu = tester.widget<Text>(find.byType(Text)).data!;
      expect(milieu, isNot(formaterArgent(0)));
      expect(milieu, isNot(formaterArgent(12000)));

      await tester.pumpAndSettle();
      expect(find.text(formaterArgent(12000)), findsOneWidget);
    });

    testWidgets('sans animation, affiche la valeur finale tout de suite', (tester) async {
      await tester.pumpWidget(enrober(const CompteurAnime(valeur: 12000), sansAnimation: true));
      expect(find.text(formaterArgent(12000)), findsOneWidget);
    });
  });

  group('BarreAnimee', () {
    double hauteur(WidgetTester tester) => tester.getSize(find.byType(BarreAnimee)).height;

    testWidgets('grandit depuis zéro jusqu\'à sa hauteur', (tester) async {
      await tester.pumpWidget(
        enrober(
          const Align(
            alignment: Alignment.bottomLeft,
            child: BarreAnimee(ratio: 0.5, hauteurMax: 100, couleur: Colors.blue),
          ),
        ),
      );
      expect(hauteur(tester), 0);
      await tester.pump(const Duration(milliseconds: 300));
      expect(hauteur(tester), inExclusiveRange(0, 50));
      await tester.pumpAndSettle();
      expect(hauteur(tester), 50);
    });

    testWidgets('sans animation, a tout de suite sa hauteur', (tester) async {
      await tester.pumpWidget(
        enrober(
          const Align(
            alignment: Alignment.bottomLeft,
            child: BarreAnimee(ratio: 0.5, hauteurMax: 100, couleur: Colors.blue),
          ),
          sansAnimation: true,
        ),
      );
      expect(hauteur(tester), 50);
    });
  });
}
