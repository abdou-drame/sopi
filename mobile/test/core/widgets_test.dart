import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/theme/couleurs.dart';
import 'package:sopi/core/widgets/bandeaux.dart';
import 'package:sopi/core/widgets/boutons.dart';
import 'package:sopi/core/widgets/champ_formulaire.dart';
import 'package:sopi/core/widgets/decompte.dart';
import 'package:sopi/core/widgets/en_tete_ecran.dart';
import 'package:sopi/core/widgets/etats.dart';
import 'package:sopi/core/widgets/ligne_montant.dart';
import 'package:sopi/core/widgets/pastille_statut.dart';

import '../aide.dart';

void main() {
  group('boutons', () {
    for (final (nom, fabrique)
        in <(String, Widget Function(String, VoidCallback?, bool))>[
          (
            'BoutonPrimaire',
            (l, p, c) =>
                BoutonPrimaire(libelle: l, onPressed: p, chargement: c),
          ),
          (
            'BoutonSecondaire',
            (l, p, c) =>
                BoutonSecondaire(libelle: l, onPressed: p, chargement: c),
          ),
        ]) {
      testWidgets('$nom : normal, désactivé et chargement', (tester) async {
        var appuis = 0;

        await tester.pumpWidget(
          enrober(fabrique('Valider', () => appuis++, false)),
        );
        expect(find.text('Valider'), findsOneWidget);
        await tester.tap(find.text('Valider'));
        expect(appuis, 1);

        await tester.pumpWidget(enrober(fabrique('Valider', null, false)));
        await tester.tap(find.text('Valider'));
        expect(appuis, 1, reason: 'désactivé');

        await tester.pumpWidget(
          enrober(fabrique('Valider', () => appuis++, true)),
        );
        expect(
          find.text('Valider'),
          findsNothing,
          reason: 'le libellé est remplacé par le cercle',
        );
        expect(find.byType(CircularProgressIndicator), findsOneWidget);
        await tester.tap(find.byType(FilledButton));
        expect(appuis, 1, reason: 'pas de double envoi pendant le chargement');
      });
    }

    testWidgets('le bouton est une pilule de 54 de haut', (tester) async {
      await tester.pumpWidget(
        enrober(BoutonPrimaire(libelle: 'Ok', onPressed: () {})),
      );
      expect(tester.getSize(find.byType(FilledButton)).height, 54);
    });
  });

  group('ChampFormulaire', () {
    testWidgets('affiche le libellé au-dessus et saisit du texte', (
      tester,
    ) async {
      final c = TextEditingController();
      await tester.pumpWidget(
        enrober(ChampFormulaire(libelle: 'Téléphone', controleur: c)),
      );
      expect(find.text('Téléphone'), findsOneWidget);
      await tester.enterText(find.byType(TextField), '771234567');
      expect(c.text, '771234567');
      expect(
        tester.getTopLeft(find.text('Téléphone')).dy,
        lessThan(tester.getTopLeft(find.byType(TextField)).dy),
      );
    });

    testWidgets("affiche l'erreur en rouge dessous", (tester) async {
      await tester.pumpWidget(
        enrober(
          const ChampFormulaire(libelle: 'E-mail', erreur: 'Adresse invalide.'),
        ),
      );
      final erreur = find.text('Adresse invalide.');
      expect(erreur, findsOneWidget);
      expect(
        tester.widget<Text>(erreur).style?.color,
        CouleursSopi.erreurTexte,
      );
      expect(
        tester.getTopLeft(erreur).dy,
        greaterThan(tester.getBottomLeft(find.byType(TextField)).dy - 1),
      );
    });

    testWidgets("l'œil montre et cache le mot de passe", (tester) async {
      await tester.pumpWidget(
        enrober(
          const ChampFormulaire(libelle: 'Mot de passe', motDePasse: true),
        ),
      );
      expect(
        tester.widget<TextField>(find.byType(TextField)).obscureText,
        isTrue,
      );
      await tester.tap(find.byIcon(Icons.visibility_outlined));
      await tester.pump();
      expect(
        tester.widget<TextField>(find.byType(TextField)).obscureText,
        isFalse,
      );
      await tester.tap(find.byIcon(Icons.visibility_off_outlined));
      await tester.pump();
      expect(
        tester.widget<TextField>(find.byType(TextField)).obscureText,
        isTrue,
      );
    });
  });

  group('EnTeteEcran', () {
    testWidgets(
      'titre en deux lignes (400 puis 800) et bouton retour rond 44×44',
      (tester) async {
        await tester.pumpWidget(
          enrober(
            const EnTeteEcran(titreLigne1: 'Mes', titreLigne2: 'réservations'),
          ),
        );
        await tester.pumpAndSettle();
        expect(
          tester.widget<Text>(find.text('Mes')).style?.fontWeight,
          FontWeight.w400,
        );
        expect(
          tester.widget<Text>(find.text('réservations')).style?.fontWeight,
          FontWeight.w800,
        );
        expect(tester.getSize(find.byType(BoutonRetour)), const Size(44, 44));
      },
    );

    testWidgets("le titre apparaît en fondu", (tester) async {
      await tester.pumpWidget(
        enrober(
          const EnTeteEcran(titreLigne1: 'Mes', titreLigne2: 'réservations'),
        ),
      );
      expect(opacite(tester, find.text('Mes')), 0);

      await tester.pumpAndSettle();
      expect(opacite(tester, find.text('Mes')), 1);
    });

    testWidgets('sans animation, tout est affiché tout de suite', (
      tester,
    ) async {
      await tester.pumpWidget(
        enrober(
          const EnTeteEcran(titreLigne1: 'Mes', titreLigne2: 'réservations'),
          sansAnimation: true,
        ),
      );
      expect(find.text('réservations'), findsOneWidget);
      expect(find.byType(Opacity), findsNothing);
    });

    testWidgets('le bouton retour revient à la page précédente', (
      tester,
    ) async {
      await tester.pumpWidget(
        enrober(
          Builder(
            builder: (context) => TextButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) =>
                      const Scaffold(body: EnTeteEcran(titreLigne1: 'Détail')),
                ),
              ),
              child: const Text('Ouvrir'),
            ),
          ),
          sansAnimation: true,
        ),
      );
      await tester.tap(find.text('Ouvrir'));
      await tester.pumpAndSettle();
      expect(find.text('Détail'), findsOneWidget);
      await tester.tap(find.byType(BoutonRetour));
      await tester.pumpAndSettle();
      expect(find.text('Détail'), findsNothing);
    });
  });

  testWidgets('PastilleStatut affiche son libellé', (tester) async {
    await tester.pumpWidget(
      enrober(
        const PastilleStatut(libelle: 'Confirmée', ton: TonStatut.succes),
      ),
    );
    expect(find.text('Confirmée'), findsOneWidget);
  });

  testWidgets('LigneMontant : libellé à gauche, valeur à droite', (
    tester,
  ) async {
    await tester.pumpWidget(
      enrober(const LigneMontant(libelle: 'Acompte', valeur: '3 000 FCFA')),
    );
    expect(
      tester.getTopLeft(find.text('Acompte')).dx,
      lessThan(tester.getTopLeft(find.text('3 000 FCFA')).dx),
    );
    expect(tester.getTopRight(find.text('3 000 FCFA')).dx, greaterThan(300));
  });

  testWidgets('les trois bandeaux affichent leur message', (tester) async {
    await tester.pumpWidget(
      enrober(
        const Column(
          children: [
            BandeauInfo(message: 'Information'),
            BandeauErreur(message: 'Problème'),
            BandeauSucces(message: 'Réussi'),
          ],
        ),
      ),
    );
    expect(find.text('Information'), findsOneWidget);
    expect(find.text('Problème'), findsOneWidget);
    expect(find.text('Réussi'), findsOneWidget);
  });

  group('états', () {
    testWidgets('EtatVide affiche un message et une action facultative', (
      tester,
    ) async {
      var appuis = 0;
      await tester.pumpWidget(
        enrober(
          EtatVide(
            message: 'Rien ici',
            libelleAction: 'Chercher',
            onAction: () => appuis++,
          ),
        ),
      );
      expect(find.text('Rien ici'), findsOneWidget);
      await tester.tap(find.text('Chercher'));
      expect(appuis, 1);
    });

    testWidgets('EtatChargement affiche un indicateur', (tester) async {
      await tester.pumpWidget(
        enrober(const EtatChargement(message: 'Un instant')),
      );
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(find.text('Un instant'), findsOneWidget);
    });

    testWidgets('EtatErreur propose « Réessayer »', (tester) async {
      var essais = 0;
      await tester.pumpWidget(
        enrober(
          EtatErreur(message: 'Pas de connexion.', onReessayer: () => essais++),
        ),
      );
      expect(find.text('Pas de connexion.'), findsOneWidget);
      await tester.tap(find.text('Réessayer'));
      expect(essais, 1);
    });
  });

  group('Decompte', () {
    testWidgets(
      'affiche mm:ss, décompte, puis prévient une seule fois à zéro',
      (tester) async {
        var maintenant = DateTime.utc(2026, 10, 24, 10);
        var appels = 0;
        await tester.pumpWidget(
          enrober(
            Decompte(
              expiration: DateTime.utc(2026, 10, 24, 10, 0, 3),
              maintenant: () => maintenant,
              onExpire: () => appels++,
            ),
          ),
        );
        expect(find.text('00:03'), findsOneWidget);

        maintenant = maintenant.add(const Duration(seconds: 1));
        await tester.pump(const Duration(seconds: 1));
        expect(find.text('00:02'), findsOneWidget);

        maintenant = maintenant.add(const Duration(seconds: 2));
        await tester.pump(const Duration(seconds: 1));
        expect(find.text('Expiré'), findsOneWidget);
        expect(appels, 1);

        await tester.pump(const Duration(seconds: 3));
        expect(appels, 1);
      },
    );

    testWidgets('affiche « 1 h 52 » au-delà d\'une heure', (tester) async {
      final maintenant = DateTime.utc(2026, 10, 24, 10);
      await tester.pumpWidget(
        enrober(
          Decompte(
            expiration: maintenant.add(const Duration(hours: 1, minutes: 52)),
            maintenant: () => maintenant,
          ),
        ),
      );
      expect(find.text('1 h 52'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    });

    testWidgets('prévient tout de suite si la date est déjà passée', (
      tester,
    ) async {
      var appels = 0;
      await tester.pumpWidget(
        enrober(
          Decompte(
            expiration: DateTime.utc(2026, 10, 24, 9),
            maintenant: () => DateTime.utc(2026, 10, 24, 10),
            onExpire: () => appels++,
          ),
        ),
      );
      await tester.pump();
      expect(appels, 1);
      expect(find.text('Expiré'), findsOneWidget);
    });
  });
}
