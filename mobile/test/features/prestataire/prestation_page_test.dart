import 'package:dio/dio.dart' show RequestOptions;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/core/widgets/code_saisie/code_saisie.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';
import 'aide_prestataire.dart';

void _vue390(WidgetTester tester) {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Map<String, Simulee> _routes(Map<String, Object?> detail) => {
  'GET /me': Simulee(200, utilisateurPrestataire()),
  'GET /prestataire/solde': const Simulee(200, {'solde': 42500}),
  'GET /prestataire/reservations': Simulee(200, pageDemandesApi([detail])),
  'GET /prestataire/reservations/r-1': Simulee(200, detail),
  'GET /notifications/non-lues/compteur': const Simulee(200, {'nonLues': 0}),
};

Future<AdaptateurSimule> _ouvrir(
  WidgetTester tester, {
  Map<String, Simulee> enPlus = const {},
  AdaptateurSimule Function(Map<String, Simulee>)? fabrique,
}) async {
  _vue390(tester);
  final routes = {..._routes(demandeApi(statut: 'confirmee')), ...enPlus};
  final serveur = fabrique == null
      ? AdaptateurSimule(routes)
      : fabrique(routes);
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: '/prestataire/reservations/r-1',
  );
  await passerTransition(tester);
  return serveur;
}

Finder get _champCode => find.byType(TextField);

String _texteAffiche(WidgetTester tester) => tester
    .widgetList<Text>(find.byType(Text))
    .map((t) => t.data ?? t.textSpan?.toPlainText() ?? '')
    .join('\n');

List<RequestOptions> _envois(AdaptateurSimule s, String fin) => s.requetes
    .where((r) => r.method == 'POST' && r.path.endsWith(fin))
    .toList();

int _lectures(AdaptateurSimule s) => s.requetes
    .where((r) => r.method == 'GET' && r.path.endsWith('/r-1'))
    .length;

void main() {
  setUpAll(initialiserFormats);

  group('Prestation (réservation confirmée)', () {
    testWidgets("montants lus de l'API, saisie du code et deux boutons", (
      tester,
    ) async {
      await _ouvrir(tester);

      expect(find.text('Rendez-vous'), findsOneWidget);
      expect(find.text('Confirmée'), findsOneWidget);
      expect(find.text('Aminata D.'), findsOneWidget);
      expect(find.text('Coupe femme · Sam. 24 oct., 10h00'), findsOneWidget);
      expect(find.text('Tarif du service'), findsOneWidget);
      expect(find.text(formaterArgent(5000)), findsOneWidget);
      expect(find.text('Acompte reçu'), findsOneWidget);
      expect(find.text(formaterArgent(1500)), findsOneWidget);
      expect(find.text('Reste à encaisser sur place'), findsOneWidget);
      expect(find.text(formaterArgent(3500)), findsOneWidget);
      expect(find.text('− ${formaterArgent(500)}'), findsOneWidget);
      expect(find.text('Crédité au portefeuille à la fin'), findsOneWidget);
      expect(find.text(formaterArgent(1000)), findsOneWidget);
      expect(find.text('Code du client'), findsOneWidget);
      expect(find.text('Démarrer la prestation'), findsOneWidget);
      expect(find.text('Annuler (le client est remboursé)'), findsOneWidget);
      // Aucun taux ni délai écrit en dur.
      expect(_texteAffiche(tester), isNot(contains('%')));
      expect(_texteAffiche(tester), isNot(contains('2 h')));
    });

    testWidgets(
      'le code est mis en majuscules, limité à 6, alphabet respecté',
      (tester) async {
        await _ouvrir(tester);
        await tester.enterText(_champCode, 'k7m0o1l');
        await tester.pump();
        // 0, O, 1 et L n'existent pas dans l'alphabet du code.
        expect(tester.widget<TextField>(_champCode).controller!.text, 'K7M');
        expect(find.text('K'), findsOneWidget);
        expect(find.text('7'), findsOneWidget);
        expect(find.text('M'), findsOneWidget);
      },
    );

    testWidgets('code complet : envoi, « Code validé », puis relecture', (
      tester,
    ) async {
      final serveur = await _ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/reservations/r-1/demarrer': Simulee(
            200,
            demandeApi(statut: 'en_cours', avecMontants: false),
          ),
        },
      );
      final avant = _lectures(serveur);
      await tester.enterText(_champCode, 'k7mq2p');
      await tester.pump();
      await avancer(tester, 2500);

      final envois = _envois(serveur, '/demarrer');
      expect(envois, hasLength(1));
      expect(envois.single.data, {'code': 'K7MQ2P'});
      expect(
        find.text('Le code est bon. Vous pouvez démarrer la prestation.'),
        findsOneWidget,
      );
      expect(
        _lectures(serveur),
        greaterThan(avant),
        reason: 'la réservation est relue',
      );
    });

    testWidgets('code faux (422) : message du serveur, nouvelle tentative', (
      tester,
    ) async {
      final serveur = await _ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/reservations/r-1/demarrer': const Simulee(422, {
            'message': 'Code de réservation incorrect.',
            'errors': {
              'code': ['Code de réservation incorrect.'],
            },
          }),
        },
      );
      await tester.enterText(_champCode, 'K7MQ2P');
      await tester.pump();
      await avancer(tester, 2500);

      expect(find.text('Code de réservation incorrect.'), findsOneWidget);
      expect(
        find.text('Le code est bon. Vous pouvez démarrer la prestation.'),
        findsNothing,
      );
      expect(
        tester.widget<TextField>(_champCode).enabled,
        isTrue,
        reason: 'on peut réessayer',
      );

      await tester.enterText(_champCode, 'K7MQ2Q');
      await tester.pump();
      await avancer(tester, 2500);
      expect(_envois(serveur, '/demarrer'), hasLength(2));
    });

    testWidgets('429 : message du serveur tel quel, sans durée inventée', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/reservations/r-1/demarrer': const Simulee(429, {
            'message': 'Trop de tentatives. Réessayez dans quelques instants.',
          }),
        },
      );
      await tester.enterText(_champCode, 'K7MQ2P');
      await tester.pump();
      await avancer(tester, 2500);

      expect(
        find.text('Trop de tentatives. Réessayez dans quelques instants.'),
        findsOneWidget,
      );
      // Pas de décompte ni de durée chiffrée inventés.
      expect(find.textContaining(RegExp(r'\d+ ?(s|min)\b')), findsNothing);
      expect(tester.widget<TextField>(_champCode).enabled, isTrue);
    });

    testWidgets('pas de double envoi : plusieurs touches, une seule requête', (
      tester,
    ) async {
      AdaptateurLentPour? lent;
      final serveur = await _ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/reservations/r-1/demarrer': Simulee(
            200,
            demandeApi(statut: 'en_cours', avecMontants: false),
          ),
        },
        fabrique: (routes) => lent = AdaptateurLentPour(routes, {
          'POST /prestataire/reservations/r-1/demarrer',
        }),
      );
      await tester.enterText(_champCode, 'K7MQ2P');
      await tester.pump();
      await avancer(tester, 1000);
      for (var i = 0; i < 3; i++) {
        await tester.tap(find.text('Démarrer la prestation'));
        await tester.pump(const Duration(milliseconds: 50));
      }
      // La requête est retenue par le faux serveur : rien n'est encore enregistré.
      expect(_envois(serveur, '/demarrer'), isEmpty);
      lent!.liberer();
      await avancer(tester, 2500);
      expect(_envois(serveur, '/demarrer'), hasLength(1));
    });

    testWidgets("« Démarrer » sans code complet n'envoie rien", (tester) async {
      final serveur = await _ouvrir(tester);
      await tester.enterText(_champCode, 'K7M');
      await tester.pump();
      await tester.tap(find.text('Démarrer la prestation'));
      await avancer(tester, 500);
      expect(_envois(serveur, '/demarrer'), isEmpty);
    });

    test("l'alphabet est celui du serveur", () {
      expect(alphabetCode, 'ABCDEFGHJKMNPQRSTUVWXYZ23456789');
    });
  });

  group('Annulation par le prestataire', () {
    Future<AdaptateurSimule> ouvrirAnnulation(
      WidgetTester tester, {
      Map<String, Simulee> enPlus = const {},
    }) async {
      final serveur = await _ouvrir(tester, enPlus: enPlus);
      await tester.tap(find.text('Annuler (le client est remboursé)'));
      await avancer(tester, 500);
      return serveur;
    }

    testWidgets("confirmation d'abord : « Non » ne fait rien", (tester) async {
      final serveur = await ouvrirAnnulation(tester);
      expect(find.text('Annuler la prestation ?'), findsOneWidget);
      await tester.tap(find.text('Non'));
      await avancer(tester, 500);
      expect(find.text('Annuler la prestation ?'), findsNothing);
      expect(cheminCourant(tester), Chemins.reservationPrestataire('r-1'));
      expect(_envois(serveur, '/annuler'), isEmpty);
    });

    testWidgets(
      'motif de 3 à 500 caractères, puis annulation et retour à la liste',
      (tester) async {
        final serveur = await ouvrirAnnulation(
          tester,
          enPlus: {
            'POST /prestataire/reservations/r-1/annuler': Simulee(
              200,
              demandeApi(
                statut: 'annulee',
                avecMontants: false,
                motifRefus: 'Panne de courant',
              ),
            ),
          },
        );
        await tester.tap(find.text('Oui, annuler'));
        await avancer(tester, 800);
        expect(cheminCourant(tester), Chemins.annulationPrestataire('r-1'));
        expect(
          find.text("Motif de l'annulation (obligatoire)"),
          findsOneWidget,
        );

        // Trop court : le bouton reste inactif.
        await tester.enterText(find.byType(TextField), 'ab');
        await tester.pump();
        await tester.tap(find.text("Confirmer l'annulation"));
        await avancer(tester, 300);
        expect(_envois(serveur, '/annuler'), isEmpty);

        await tester.enterText(find.byType(TextField), '  Panne de courant  ');
        await tester.pump();
        await tester.tap(find.text("Confirmer l'annulation"));
        await passerTransition(tester);

        final envoi = _envois(serveur, '/annuler').single;
        expect(envoi.data, {'motif': 'Panne de courant'});
        expect(cheminCourant(tester), Chemins.reservationsPrestataire);
      },
    );

    testWidgets('refus du serveur : message affiché, on reste sur le motif', (
      tester,
    ) async {
      await ouvrirAnnulation(
        tester,
        enPlus: {
          'POST /prestataire/reservations/r-1/annuler': const Simulee(409, {
            'message': 'Le créneau a déjà commencé.',
          }),
        },
      );
      await tester.tap(find.text('Oui, annuler'));
      await avancer(tester, 800);
      await tester.enterText(find.byType(TextField), 'Panne de courant');
      await tester.pump();
      await tester.tap(find.text("Confirmer l'annulation"));
      await passerTransition(tester);
      expect(find.text('Le créneau a déjà commencé.'), findsOneWidget);
      expect(cheminCourant(tester), Chemins.annulationPrestataire('r-1'));
    });
  });
}
