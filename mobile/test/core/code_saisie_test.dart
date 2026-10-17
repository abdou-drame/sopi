import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/api/erreurs.dart';
import 'package:sopi/core/widgets/code_saisie/code_saisie.dart';

import '../aide.dart';

const _codeFaux = 'Code de réservation incorrect.';

/// Fait avancer le temps par pas de 50 ms (le curseur clignote sans fin : pas de pumpAndSettle).
Future<void> _avancer(WidgetTester tester, int ms) async {
  for (var t = 0; t < ms; t += 50) {
    await tester.pump(const Duration(milliseconds: 50));
  }
}

void main() {
  group('FormateurCode.nettoyer', () {
    test('majuscules, sans espaces, sans caractère hors alphabet', () {
      expect(FormateurCode.nettoyer('ab c-d'), 'ABCD');
      expect(FormateurCode.nettoyer(' a b c '), 'ABC');
    });

    test('refuse les caractères ambigus 0, O, 1, I, L', () {
      expect(FormateurCode.nettoyer('0O1ILA'), 'A');
    });

    test('tronque à 6 caractères (collage d\'un texte long)', () {
      expect(FormateurCode.nettoyer('abcdefghjk'), 'ABCDEF');
      expect(FormateurCode.nettoyer('Code : K7M 2PQ 9'), 'CDEK7M');
    });
  });

  group('saisie', () {
    testWidgets('filtre la frappe et affiche les lettres dans les cases', (
      tester,
    ) async {
      await tester.pumpWidget(
        enrober(CodeSaisie(onSubmit: (_) async {}), sansAnimation: true),
      );
      await tester.enterText(find.byType(TextField), 'a-b 0c');
      await tester.pump();
      for (final l in ['A', 'B', 'C']) {
        expect(find.text(l), findsOneWidget);
      }
      expect(find.text('0'), findsNothing);
    });

    testWidgets('un code collé est nettoyé et tronqué, puis envoyé', (
      tester,
    ) async {
      String? recu;
      await tester.pumpWidget(
        enrober(
          CodeSaisie(onSubmit: (c) async => recu = c),
          sansAnimation: true,
        ),
      );
      await tester.enterText(find.byType(TextField), '  k7m 2pq9 zz ');
      await tester.pump();
      expect(recu, 'K7M2PQ');
    });

    testWidgets(
      'la suppression retire le dernier caractère et rien n\'est envoyé avant 6',
      (tester) async {
        var envois = 0;
        await tester.pumpWidget(
          enrober(
            CodeSaisie(onSubmit: (_) async => envois++),
            sansAnimation: true,
          ),
        );
        await tester.enterText(find.byType(TextField), 'ABCDE');
        await tester.pump();
        await tester.enterText(find.byType(TextField), 'ABCD');
        await tester.pump();
        expect(find.text('E'), findsNothing);
        expect(find.text('D'), findsOneWidget);
        expect(envois, 0);
      },
    );
  });

  group('états (sans animation)', () {
    testWidgets('vérification puis succès', (tester) async {
      final reponse = Completer<void>();
      var succes = 0;
      await tester.pumpWidget(
        enrober(
          CodeSaisie(onSubmit: (_) => reponse.future, onSucces: () => succes++),
          sansAnimation: true,
        ),
      );
      await tester.enterText(find.byType(TextField), 'ABCDEF');
      await tester.pump();
      expect(
        find.text('Un instant, nous confirmons le code du client.'),
        findsOneWidget,
      );
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(succes, 0);

      reponse.complete();
      await tester.pump();
      await tester.pump();
      expect(
        find.text('Le code est bon. Vous pouvez démarrer la prestation.'),
        findsOneWidget,
      );
      expect(succes, 1);
    });

    testWidgets(
      'code faux : message du serveur en rouge, cases de retour, nouvelle saisie possible',
      (tester) async {
        var envois = 0;
        await tester.pumpWidget(
          enrober(
            CodeSaisie(
              onSubmit: (_) async {
                envois++;
                throw const ErreurValidation('Données invalides.', {
                  'code': [_codeFaux],
                });
              },
            ),
            sansAnimation: true,
          ),
        );
        await tester.enterText(find.byType(TextField), 'ABCDEF');
        await tester.pump();
        await tester.pump();
        await tester.pump();

        final message = find.text(_codeFaux);
        expect(message, findsOneWidget);
        expect(
          tester.widget<Text>(message).style?.color,
          const Color(0xFFB91C1C),
        );
        expect(find.byType(CircularProgressIndicator), findsNothing);
        expect(find.text('A'), findsOneWidget, reason: 'les cases reviennent');

        // Toute modification efface l'erreur ; on peut réessayer.
        await tester.enterText(find.byType(TextField), 'ABCDE');
        await tester.pump();
        expect(find.text(_codeFaux), findsNothing);
        await tester.enterText(find.byType(TextField), 'ABCDEG');
        await tester.pump();
        await tester.pump();
        await tester.pump();
        expect(envois, 2);
      },
    );

    testWidgets('autre erreur (réseau) : son message est affiché', (
      tester,
    ) async {
      await tester.pumpWidget(
        enrober(
          CodeSaisie(onSubmit: (_) async => throw const ErreurReseau()),
          sansAnimation: true,
        ),
      );
      await tester.enterText(find.byType(TextField), 'ABCDEF');
      await tester.pump();
      await tester.pump();
      await tester.pump();
      expect(find.text(messageReseau), findsOneWidget);
    });

    testWidgets(
      '429 : le champ se bloque avec le délai de Retry-After, puis se libère',
      (tester) async {
        await tester.pumpWidget(
          enrober(
            CodeSaisie(
              onSubmit: (_) async => throw const ErreurTropDeRequetes(
                'Trop de tentatives.',
                delai: Duration(seconds: 3),
              ),
            ),
            sansAnimation: true,
          ),
        );
        await tester.enterText(find.byType(TextField), 'ABCDEF');
        await tester.pump();
        await tester.pump();
        await tester.pump();

        expect(
          find.text('Trop de tentatives. Réessayez dans 3 s.'),
          findsOneWidget,
        );
        expect(
          tester.widget<TextField>(find.byType(TextField)).enabled,
          isFalse,
        );

        await tester.pump(const Duration(seconds: 1));
        expect(
          find.text('Trop de tentatives. Réessayez dans 2 s.'),
          findsOneWidget,
        );

        await tester.pump(const Duration(seconds: 2));
        expect(find.textContaining('Réessayez dans'), findsNothing);
        expect(
          tester.widget<TextField>(find.byType(TextField)).enabled,
          isTrue,
        );
        expect(
          find.text('A'),
          findsNothing,
          reason: 'le code précédent est effacé',
        );
      },
    );
    testWidgets(
      '429 sans Retry-After : message du serveur tel quel, sans durée inventée, champ libre',
      (tester) async {
        await tester.pumpWidget(
          enrober(
            CodeSaisie(
              onSubmit: (_) async =>
                  throw const ErreurTropDeRequetes('Patientez un instant.'),
            ),
            sansAnimation: true,
          ),
        );
        await tester.enterText(find.byType(TextField), 'ABCDEF');
        await tester.pump();
        await tester.pump();
        await tester.pump();

        expect(find.text('Patientez un instant.'), findsOneWidget);
        expect(find.textContaining('Réessayez dans'), findsNothing);
        expect(
          tester.widget<TextField>(find.byType(TextField)).enabled,
          isTrue,
        );
      },
    );
  });

  group('animations', () {
    testWidgets(
      'avec animations : éventail, fusion, vérification, puis coche',
      (tester) async {
        final reponse = Completer<void>();
        var succes = 0;
        await tester.pumpWidget(
          enrober(
            CodeSaisie(
              onSubmit: (_) => reponse.future,
              onSucces: () => succes++,
            ),
          ),
        );
        await tester.enterText(find.byType(TextField), 'ABCDEF');
        await tester.pump();
        expect(
          find.text('Un instant, nous confirmons le code du client.'),
          findsOneWidget,
        );
        expect(
          find.text('Le code est bon. Vous pouvez démarrer la prestation.'),
          findsNothing,
        );

        await _avancer(tester, 900); // éventail 350 ms + fusion 300 ms
        expect(find.byType(CircularProgressIndicator), findsOneWidget);
        expect(succes, 0);

        reponse.complete();
        await _avancer(tester, 1000); // coche et rebond
        expect(
          find.text('Le code est bon. Vous pouvez démarrer la prestation.'),
          findsOneWidget,
        );
        expect(succes, 1);
        await tester.pumpWidget(const SizedBox());
      },
    );

    testWidgets(
      'avec animations : code faux, les cases reviennent puis le message apparaît',
      (tester) async {
        await tester.pumpWidget(
          enrober(
            CodeSaisie(
              onSubmit: (_) async => throw const ErreurValidation('Invalide.', {
                'code': [_codeFaux],
              }),
            ),
          ),
        );
        await tester.enterText(find.byType(TextField), 'ABCDEF');
        await tester.pump();
        expect(find.text(_codeFaux), findsNothing);

        // éventail, fusion, puis fusion et éventail à l'envers
        await _avancer(tester, 2200);
        expect(find.text(_codeFaux), findsOneWidget);
        expect(find.byType(CircularProgressIndicator), findsNothing);
        await _avancer(tester, 500); // secousse
        expect(find.text(_codeFaux), findsOneWidget);
        await tester.pumpWidget(const SizedBox());
      },
    );
  });
}
