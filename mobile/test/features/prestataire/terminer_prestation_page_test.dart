import 'package:dio/dio.dart' show RequestOptions;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

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

Future<AdaptateurSimule> _ouvrir(
  WidgetTester tester, {
  String statut = 'en_cours',
  Map<String, Simulee> enPlus = const {},
  AdaptateurSimule Function(Map<String, Simulee>)? fabrique,
}) async {
  _vue390(tester);
  final detail = demandeApi(statut: statut);
  final routes = <String, Simulee>{
    'GET /me': Simulee(200, utilisateurPrestataire()),
    'GET /prestataire/solde': const Simulee(200, {'solde': 42500}),
    'GET /prestataire/reservations': Simulee(200, pageDemandesApi([detail])),
    'GET /prestataire/reservations/r-1': Simulee(200, detail),
    'GET /notifications/non-lues/compteur': const Simulee(200, {'nonLues': 0}),
    ...enPlus,
  };
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

String _texteAffiche(WidgetTester tester) => tester
    .widgetList<Text>(find.byType(Text))
    .map((t) => t.data ?? t.textSpan?.toPlainText() ?? '')
    .join('\n');

List<RequestOptions> _envois(AdaptateurSimule s) => s.requetes
    .where((r) => r.method == 'POST' && r.path.endsWith('/terminer'))
    .toList();

Map<String, Object?> _reponseTerminer({
  num? montantCredite = 1000,
  num? soldeApres = 43500,
}) => {
  ...demandeApi(statut: 'terminee'),
  'montantTotal': 5000,
  'commission': montantCredite == null ? null : 500,
  'montantCredite': montantCredite,
  'soldeApres': soldeApres,
};

void main() {
  setUpAll(initialiserFormats);

  group('Terminer la prestation', () {
    testWidgets(
      'réservation en cours : écran PrestaTerminer, montants de l\'API',
      (tester) async {
        await _ouvrir(tester);
        expect(find.text('Prestation'), findsOneWidget);
        expect(find.text('En cours'), findsOneWidget);
        expect(find.text('Aminata D.'), findsOneWidget);
        expect(find.text('Tarif du service'), findsOneWidget);
        expect(find.text(formaterArgent(5000)), findsOneWidget);
        expect(find.text('Acompte reçu'), findsOneWidget);
        expect(find.text(formaterArgent(1500)), findsOneWidget);
        expect(find.text('Reste à encaisser sur place'), findsOneWidget);
        expect(find.text(formaterArgent(3500)), findsOneWidget);
        expect(find.text('− ${formaterArgent(500)}'), findsOneWidget);
        expect(find.text('Crédité à la fin'), findsOneWidget);
        expect(find.text(formaterArgent(1000)), findsOneWidget);
        expect(find.text('Terminer la prestation'), findsOneWidget);
        // Décision : « Signaler un problème » est retiré, rien n'est branché.
        expect(find.textContaining('Signaler'), findsNothing);
        expect(_texteAffiche(tester), isNot(contains('%')));
        // Pas de saisie de code sur une prestation déjà démarrée.
        expect(find.byType(TextField), findsNothing);
      },
    );

    testWidgets('« Non » à la confirmation : rien n\'est envoyé', (
      tester,
    ) async {
      final serveur = await _ouvrir(tester);
      await tester.tap(find.text('Terminer la prestation'));
      await avancer(tester, 500);
      expect(find.text('Terminer la prestation ?'), findsOneWidget);
      await tester.tap(find.text('Non'));
      await avancer(tester, 500);
      expect(_envois(serveur), isEmpty);
      expect(cheminCourant(tester), Chemins.reservationPrestataire('r-1'));
    });

    testWidgets(
      'succès : montant réellement crédité lu de l\'API, retour au tableau avec le solde relu',
      (tester) async {
        final routes = <String, Simulee>{
          'POST /prestataire/reservations/r-1/terminer': Simulee(
            200,
            _reponseTerminer(montantCredite: 1250, soldeApres: 43750),
          ),
        };
        final serveur = await _ouvrir(tester, enPlus: routes);
        // Le solde relu après le crédit.
        serveur.routes['GET /prestataire/solde'] = const Simulee(200, {
          'solde': 43750,
        });

        await tester.tap(find.text('Terminer la prestation'));
        await avancer(tester, 500);
        await tester.tap(find.text('Oui, terminer'));
        await passerTransition(tester);

        expect(_envois(serveur), hasLength(1));
        expect(
          find.text(
            'Prestation terminée. ${formaterArgent(1250)} ajoutés à votre portefeuille.',
          ),
          findsOneWidget,
          reason: 'le montant vient de la réponse, pas de la carte',
        );
        expect(cheminCourant(tester), Chemins.tableau);
        expect(find.textContaining(formaterArgent(43750)), findsWidgets);
      },
    );

    testWidgets('aucun crédit renvoyé : message simple, sans montant inventé', (
      tester,
    ) async {
      final serveur = await _ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/reservations/r-1/terminer': Simulee(
            200,
            _reponseTerminer(montantCredite: null, soldeApres: null),
          ),
        },
      );
      await tester.tap(find.text('Terminer la prestation'));
      await avancer(tester, 500);
      await tester.tap(find.text('Oui, terminer'));
      await passerTransition(tester);
      expect(_envois(serveur), hasLength(1));
      expect(find.text('Prestation terminée.'), findsOneWidget);
    });

    testWidgets('409 : message du serveur, on reste sur l\'écran', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/reservations/r-1/terminer': const Simulee(409, {
            'message': 'Cette réservation ne peut pas être terminée.',
          }),
        },
      );
      await tester.tap(find.text('Terminer la prestation'));
      await avancer(tester, 500);
      await tester.tap(find.text('Oui, terminer'));
      await passerTransition(tester);
      expect(
        find.text('Cette réservation ne peut pas être terminée.'),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.reservationPrestataire('r-1'));
    });

    testWidgets('pas de double envoi pendant l\'appel', (tester) async {
      AdaptateurLentPour? lent;
      final serveur = await _ouvrir(
        tester,
        enPlus: {
          'POST /prestataire/reservations/r-1/terminer': Simulee(
            200,
            _reponseTerminer(),
          ),
        },
        fabrique: (routes) => lent = AdaptateurLentPour(routes, {
          'POST /prestataire/reservations/r-1/terminer',
        }),
      );
      await tester.tap(find.text('Terminer la prestation'));
      await avancer(tester, 500);
      await tester.tap(find.text('Oui, terminer'));
      await avancer(tester, 300);
      // Le bouton est en chargement : de nouvelles touches ne font rien.
      await tester.tap(find.byType(FilledButton), warnIfMissed: false);
      await avancer(tester, 300);
      expect(find.text('Terminer la prestation ?'), findsNothing);
      lent!.liberer();
      await passerTransition(tester);
      expect(_envois(serveur), hasLength(1));
    });

    testWidgets('statut confirmé : on voit Prestation, pas Terminer', (
      tester,
    ) async {
      await _ouvrir(tester, statut: 'confirmee');
      expect(find.text('Code du client'), findsOneWidget);
      expect(find.text('Terminer la prestation'), findsNothing);
    });
  });
}
