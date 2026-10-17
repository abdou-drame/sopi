import 'package:dio/dio.dart' show RequestOptions;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/core/widgets/interrupteur_sopi.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_prestataire.dart';

void _vue390(WidgetTester tester) {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Map<String, Object?> _prestation(
  String id,
  String nom,
  int duree,
  num prix, {
  bool actif = true,
}) => {
  'id': id,
  'nom': nom,
  'domaine': 'Coiffure',
  'dureeMinutes': duree,
  'prix': prix,
  'actif': actif,
};

Future<AdaptateurSimule> _ouvrir(
  WidgetTester tester, {
  String chemin = '/prestataire/services',
  Map<String, Simulee> enPlus = const {},
  List<Map<String, Object?>>? liste,
}) async {
  _vue390(tester);
  final serveur = AdaptateurSimule({
    'GET /me': Simulee(200, utilisateurPrestataire()),
    'GET /prestataire/solde': const Simulee(200, {'solde': 42500}),
    'GET /prestataire/reservations': Simulee(200, pageDemandesApi([])),
    'GET /notifications/non-lues/compteur': const Simulee(200, {'nonLues': 0}),
    'GET /prestataire/prestations': Simulee(200, {
      'data':
          liste ??
          [
            _prestation('p-1', 'Coupe femme', 60, 5000),
            _prestation('p-2', 'Brushing', 45, 3000),
            _prestation('p-3', 'Soin hydratant', 60, 4000, actif: false),
          ],
    }),
    ...enPlus,
  });
  await lancerAcces(tester, serveur, jeton: 'jeton', chemin: chemin);
  await passerTransition(tester);
  return serveur;
}

List<RequestOptions> _ecritures(AdaptateurSimule s) =>
    s.requetes.where((r) => r.method != 'GET').toList();

String _texteAffiche(WidgetTester tester) => tester
    .widgetList<Text>(find.byType(Text))
    .map((t) => t.data ?? t.textSpan?.toPlainText() ?? '')
    .join('\n');

Finder get _champs => find.byType(TextField);

void main() {
  setUpAll(initialiserFormats);

  group('Liste des services', () {
    testWidgets('actifs et désactivés, sans acompte ni suppression', (
      tester,
    ) async {
      await _ouvrir(tester);
      expect(find.text('Coupe femme'), findsOneWidget);
      expect(find.text('1 h'), findsOneWidget);
      expect(find.text(formaterArgent(5000)), findsOneWidget);
      expect(find.text('Brushing'), findsOneWidget);
      expect(find.text('45 min'), findsOneWidget);
      expect(find.text('Soin hydratant'), findsOneWidget);
      expect(find.text('Désactivé'), findsOneWidget);
      expect(find.text(formaterArgent(4000)), findsOneWidget);
      expect(find.text('Ajouter un service'), findsOneWidget);
      expect(find.byIcon(Icons.edit_outlined), findsNWidgets(3));
      expect(find.textContaining('compte'), findsNothing);
      expect(find.textContaining('Supprimer'), findsNothing);
      expect(_texteAffiche(tester), isNot(contains('%')));
    });

    testWidgets('liste vide', (tester) async {
      await _ouvrir(tester, liste: []);
      expect(find.text('Aucun service'), findsOneWidget);
      expect(find.text('Ajouter un service'), findsOneWidget);
    });

    testWidgets('erreur serveur : message et bouton Réessayer', (tester) async {
      await _ouvrir(
        tester,
        enPlus: {
          'GET /prestataire/prestations': const Simulee(503, {
            'message': 'Service indisponible.',
          }),
        },
      );
      expect(find.text('Service indisponible.'), findsOneWidget);
    });

    testWidgets('« Ajouter un service » ouvre le formulaire vide', (
      tester,
    ) async {
      await _ouvrir(tester);
      await tester.tap(find.text('Ajouter un service'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.serviceNouveau);
      expect(find.text('Nom du service'), findsOneWidget);
      expect(find.text('Prix (FCFA)'), findsOneWidget);
    });

    testWidgets('le crayon ouvre la modification du service', (tester) async {
      await _ouvrir(tester);
      await tester.tap(find.byIcon(Icons.edit_outlined).at(1));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.serviceModifier('p-2'));
      expect(find.text('Modifier le'), findsOneWidget);
    });
  });

  group('Formulaire de service', () {
    testWidgets('création : champs, puces, info acompte, interrupteur', (
      tester,
    ) async {
      await _ouvrir(tester, chemin: Chemins.serviceNouveau);
      expect(find.text('Ajouter un'), findsOneWidget);
      for (final d in ['30 min', '1 h', '1 h 30', '2 h', '3 h']) {
        expect(find.text(d), findsOneWidget);
      }
      expect(
        find.text(
          "L'acompte demandé au client est calculé automatiquement par Sopi.",
        ),
        findsOneWidget,
      );
      expect(find.text('Service actif'), findsOneWidget);
      expect(
        find.text('Visible et réservable par les clients'),
        findsOneWidget,
      );
      expect(find.text('Enregistrer'), findsOneWidget);
      expect(find.textContaining('Supprimer'), findsNothing);
    });

    testWidgets(
      'création : envoie nom, durée et prix, puis retour à la liste',
      (tester) async {
        final serveur = await _ouvrir(
          tester,
          enPlus: {
            'POST /prestataire/prestations': Simulee(201, {
              'data': _prestation('p-9', 'Tresses', 180, 12000),
            }),
          },
        );
        await tester.tap(find.text('Ajouter un service'));
        await passerTransition(tester);
        await tester.enterText(_champs.at(0), '  Tresses ');
        await tester.enterText(_champs.at(1), '12000');
        await tester.tap(find.text('3 h'));
        await tester.pump();
        await tester.tap(find.text('Enregistrer'));
        await passerTransition(tester);

        final envoi = _ecritures(serveur).single;
        expect(envoi.method, 'POST');
        expect(envoi.path, '/prestataire/prestations');
        expect(envoi.data, {
          'nom': 'Tresses',
          'duree_minutes': 180,
          'prix': 12000,
        });
        expect(cheminCourant(tester), Chemins.services);
        expect(find.text('Service enregistré.'), findsOneWidget);
      },
    );

    testWidgets('champs vides : messages locaux, rien n\'est envoyé', (
      tester,
    ) async {
      final serveur = await _ouvrir(tester, chemin: Chemins.serviceNouveau);
      await tester.tap(find.text('Enregistrer'));
      await tester.pump();
      expect(find.text('Indiquez le nom du service.'), findsOneWidget);
      expect(find.text('Indiquez le prix du service.'), findsOneWidget);
      expect(_ecritures(serveur), isEmpty);
    });

    testWidgets(
      '422 : une erreur sous chaque champ, on reste sur le formulaire',
      (tester) async {
        await _ouvrir(
          tester,
          chemin: Chemins.serviceNouveau,
          enPlus: {
            'POST /prestataire/prestations': const Simulee(422, {
              'message': 'Les données sont invalides.',
              'errors': {
                'nom': ['Vous proposez déjà une prestation avec ce nom.'],
                'prix': ['Le prix doit être supérieur à zéro.'],
              },
            }),
          },
        );
        await tester.enterText(_champs.at(0), 'Coupe femme');
        await tester.enterText(_champs.at(1), '0');
        await tester.tap(find.text('Enregistrer'));
        await passerTransition(tester);
        expect(
          find.text('Vous proposez déjà une prestation avec ce nom.'),
          findsOneWidget,
        );
        expect(
          find.text('Le prix doit être supérieur à zéro.'),
          findsOneWidget,
        );
        expect(cheminCourant(tester), Chemins.serviceNouveau);
        // Corriger un champ efface son erreur.
        await tester.enterText(_champs.at(0), 'Coupe');
        await tester.pump();
        expect(
          find.text('Vous proposez déjà une prestation avec ce nom.'),
          findsNothing,
        );
      },
    );

    testWidgets('erreur réseau : message affiché, on peut réessayer', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        chemin: Chemins.serviceNouveau,
        enPlus: {
          'POST /prestataire/prestations': const Simulee(503, {
            'message': 'Service momentanément indisponible.',
          }),
        },
      );
      await tester.enterText(_champs.at(0), 'Tresses');
      await tester.enterText(_champs.at(1), '12000');
      await tester.tap(find.text('Enregistrer'));
      await passerTransition(tester);
      expect(find.text('Service momentanément indisponible.'), findsOneWidget);
      expect(cheminCourant(tester), Chemins.serviceNouveau);
      expect(tester.widget<TextField>(_champs.at(0)).enabled, isTrue);
    });

    testWidgets(
      'modification : préremplie, PATCH partiel (le champ changé seul)',
      (tester) async {
        final serveur = await _ouvrir(
          tester,
          chemin: Chemins.serviceModifier('p-1'),
          enPlus: {
            'PATCH /prestataire/prestations/p-1': Simulee(200, {
              'data': _prestation('p-1', 'Coupe femme', 60, 5500),
            }),
          },
        );
        expect(find.text('Modifier le'), findsOneWidget);
        expect(
          tester.widget<TextField>(_champs.at(0)).controller!.text,
          'Coupe femme',
        );
        expect(
          tester.widget<TextField>(_champs.at(1)).controller!.text,
          '5000',
        );
        await tester.enterText(_champs.at(1), '5500');
        await tester.tap(find.text('Enregistrer'));
        await passerTransition(tester);

        final envoi = _ecritures(serveur).single;
        expect(envoi.method, 'PATCH');
        expect(envoi.path, '/prestataire/prestations/p-1');
        expect(envoi.data, {'prix': 5500});
      },
    );

    testWidgets(
      'désactiver : l\'interrupteur envoie actif=false, jamais de DELETE',
      (tester) async {
        final serveur = await _ouvrir(
          tester,
          chemin: Chemins.serviceModifier('p-1'),
          enPlus: {
            'PATCH /prestataire/prestations/p-1': Simulee(200, {
              'data': _prestation('p-1', 'Coupe femme', 60, 5000, actif: false),
            }),
          },
        );
        await tester.tap(find.byType(InterrupteurSopi));
        await tester.pump();
        await tester.tap(find.text('Enregistrer'));
        await passerTransition(tester);
        final envoi = _ecritures(serveur).single;
        expect(envoi.method, 'PATCH');
        expect(envoi.data, {'actif': false});
        expect(serveur.requetes.where((r) => r.method == 'DELETE'), isEmpty);
      },
    );

    testWidgets('rien de changé : aucune requête, retour à la liste', (
      tester,
    ) async {
      final serveur = await _ouvrir(tester);
      await tester.tap(find.byIcon(Icons.edit_outlined).first);
      await passerTransition(tester);
      await tester.tap(find.text('Enregistrer'));
      await passerTransition(tester);
      expect(_ecritures(serveur), isEmpty);
      expect(cheminCourant(tester), Chemins.services);
    });

    testWidgets('durée hors puces : elle reste proposée et sélectionnée', (
      tester,
    ) async {
      await _ouvrir(tester, chemin: Chemins.serviceModifier('p-2'));
      expect(find.text('45 min'), findsOneWidget);
    });

    testWidgets('service inconnu : message du serveur', (tester) async {
      await _ouvrir(tester, chemin: Chemins.serviceModifier('inconnu'));
      expect(find.text('Service introuvable'), findsOneWidget);
    });
  });
}
