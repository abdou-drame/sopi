import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/animation/chargement.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/prestataire/formats_prestataire.dart';
import 'package:sopi/features/reservation/depot_reservation.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';
import 'aide_prestataire.dart';

/// 11 h 20 avant l'échéance de réponse des demandes simulées (12 h 00 UTC).
DateTime _maintenant() => DateTime.utc(2026, 10, 24, 0, 40);

void _vue390(WidgetTester tester) {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Future<AdaptateurSimule> _ouvrir(
  WidgetTester tester,
  Map<String, Object?> detail, {
  Map<String, Simulee> enPlus = const {},
  String chemin = '/prestataire/reservations/r-1',
}) async {
  _vue390(tester);
  final serveur = AdaptateurSimule({
    'GET /me': Simulee(200, utilisateurPrestataire()),
    'GET /prestataire/solde': const Simulee(200, {'solde': 42500}),
    'GET /prestataire/reservations': Simulee(
      200,
      pageDemandesApi([demandeApi()]),
    ),
    'GET /prestataire/reservations/r-1': Simulee(200, detail),
    'GET /notifications/non-lues/compteur': const Simulee(200, {'nonLues': 0}),
    ...enPlus,
  });
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: chemin,
    surcharges: [horlogeProvider.overrideWithValue(_maintenant)],
  );
  await passerTransition(tester);
  return serveur;
}

/// Tout le texte affiché, y compris les textes riches.
String _texteAffiche(WidgetTester tester) => tester
    .widgetList<Text>(find.byType(Text))
    .map((t) => t.data ?? t.textSpan?.toPlainText() ?? '')
    .join('\n');

void main() {
  setUpAll(initialiserFormats);

  group('formaterResteVous', () {
    test('avec du temps restant', () {
      expect(
        formaterResteVous(const Duration(hours: 11, minutes: 20)),
        'Il vous reste 11 h 20 pour répondre. Passé ce délai, la demande est annulée.',
      );
    });

    test('délai dépassé', () {
      expect(
        formaterResteVous(Duration.zero),
        startsWith('Le délai de réponse est dépassé'),
      );
    });
  });

  group('Détail d\'une demande', () {
    testWidgets('montants, délai et boutons, sans aucun pourcentage', (
      tester,
    ) async {
      await _ouvrir(tester, demandeApi());

      expect(cheminCourant(tester), '/prestataire/reservations/r-1');
      expect(find.text('Demande'), findsOneWidget);
      expect(find.text('En attente de votre réponse'), findsOneWidget);
      expect(find.text('Aminata D.'), findsOneWidget);
      expect(find.text('Coupe femme · Sam. 24 oct., 10h00'), findsOneWidget);
      expect(find.text('Tarif du service'), findsOneWidget);
      expect(find.text(formaterArgent(5000)), findsOneWidget);
      expect(find.text('Acompte demandé au client'), findsOneWidget);
      expect(find.text(formaterArgent(1500)), findsOneWidget);
      // Reste à encaisser = prix − acompte, calculé pour l'affichage.
      expect(find.text('Reste à encaisser sur place'), findsOneWidget);
      expect(find.text(formaterArgent(3500)), findsOneWidget);
      expect(find.text('Commission Sopi'), findsOneWidget);
      expect(find.text('− ${formaterArgent(500)}'), findsOneWidget);
      expect(find.text('Crédité au portefeuille à la fin'), findsOneWidget);
      expect(find.text(formaterArgent(1000)), findsOneWidget);
      expect(
        find.text(
          'Il vous reste 11 h 20 pour répondre. Passé ce délai, la demande est annulée.',
        ),
        findsOneWidget,
      );
      expect(
        find.textContaining("Le client dispose d'un délai pour payer"),
        findsOneWidget,
      );
      expect(find.text('Accepter la demande'), findsOneWidget);
      expect(find.text('Refuser'), findsOneWidget);
      // Aucun taux, ni déduit ni en dur.
      expect(_texteAffiche(tester), isNot(contains('%')));
      expect(_texteAffiche(tester), isNot(contains('2 h')));
    });

    testWidgets('demande acceptée : montants, statut, sans délai ni boutons', (
      tester,
    ) async {
      await _ouvrir(tester, demandeApi(statut: 'acceptee'));
      expect(find.text('Acceptée, en attente du paiement'), findsOneWidget);
      expect(find.text('Tarif du service'), findsOneWidget);
      expect(find.textContaining('Il vous reste'), findsNothing);
      expect(find.text('Accepter la demande'), findsNothing);
      expect(find.text('Refuser'), findsNothing);
    });

    testWidgets('demande refusée : le motif est affiché', (tester) async {
      await _ouvrir(
        tester,
        demandeApi(statut: 'refusee', motifRefus: 'Je suis absent ce jour-là.'),
      );
      expect(find.text('Refusée'), findsOneWidget);
      expect(find.text('Je suis absent ce jour-là.'), findsOneWidget);
      expect(find.text('Accepter la demande'), findsNothing);
    });

    testWidgets('chargement : squelettes, puis contenu', (tester) async {
      _vue390(tester);
      final serveur = AdaptateurLentPour(
        {
          'GET /me': Simulee(200, utilisateurPrestataire()),
          'GET /prestataire/reservations/r-1': Simulee(200, demandeApi()),
          'GET /notifications/non-lues/compteur': const Simulee(200, {
            'nonLues': 0,
          }),
        },
        {'GET /prestataire/reservations/r-1'},
      );
      await lancerAcces(
        tester,
        serveur,
        jeton: 'jeton',
        chemin: '/prestataire/reservations/r-1',
        surcharges: [horlogeProvider.overrideWithValue(_maintenant)],
      );
      await avancer(tester, 100);
      expect(find.byType(Squelette), findsWidgets);
      expect(find.text('Accepter la demande'), findsNothing);
      serveur.liberer();
      await passerTransition(tester);
      expect(find.text('Aminata D.'), findsOneWidget);
    });

    testWidgets('erreur : message du serveur et Réessayer', (tester) async {
      await _ouvrir(
        tester,
        const {},
        enPlus: {
          'GET /prestataire/reservations/r-1': const Simulee(404, {
            'message': 'Réservation introuvable.',
          }),
        },
      );
      expect(find.text('Réservation introuvable.'), findsOneWidget);
      expect(find.text('Réessayer'), findsOneWidget);
    });

    testWidgets('Accepter appelle l\'API puis relit le détail', (tester) async {
      final serveur = await _ouvrir(
        tester,
        demandeApi(),
        enPlus: {
          'POST /prestataire/reservations/r-1/accepter': Simulee(
            200,
            demandeApi(statut: 'acceptee', avecMontants: false),
          ),
        },
      );
      await tester.tap(find.text('Accepter la demande'));
      await passerTransition(tester);
      expect(
        serveur.requetes.where(
          (r) => r.method == 'POST' && r.path.endsWith('/accepter'),
        ),
        hasLength(1),
      );
      final lectures = serveur.requetes.where(
        (r) => r.method == 'GET' && r.path == '/prestataire/reservations/r-1',
      );
      expect(lectures.length, greaterThanOrEqualTo(2));
    });

    testWidgets('Accepter refusé par le serveur : son message s\'affiche', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        demandeApi(),
        enPlus: {
          'POST /prestataire/reservations/r-1/accepter': const Simulee(409, {
            'message': 'Cette demande a déjà été traitée.',
          }),
        },
      );
      await tester.tap(find.text('Accepter la demande'));
      await passerTransition(tester);
      expect(find.text('Cette demande a déjà été traitée.'), findsOneWidget);
    });

    testWidgets('Refuser ouvre l\'écran du motif', (tester) async {
      await _ouvrir(tester, demandeApi());
      await tester.tap(find.text('Refuser'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.refusReservation('r-1'));
      expect(find.text('Motif du refus (obligatoire)'), findsOneWidget);
    });
  });

  group('Motif du refus', () {
    Future<AdaptateurSimule> ouvrirRefus(
      WidgetTester tester, {
      Map<String, Simulee> enPlus = const {},
    }) => _ouvrir(
      tester,
      demandeApi(),
      chemin: Chemins.refusReservation('r-1'),
      enPlus: enPlus,
    );

    testWidgets('résumé, compteur et bouton inactif sous 3 caractères', (
      tester,
    ) async {
      await ouvrirRefus(tester);
      expect(find.text('Aminata D.'), findsOneWidget);
      expect(
        find.text('Coupe femme · ${formaterArgent(5000)}'),
        findsOneWidget,
      );
      expect(find.text('3 caractères minimum'), findsOneWidget);
      expect(find.text('0 / 500'), findsOneWidget);
      expect(
        find.text(
          'Le client verra ce motif et pourra choisir un autre créneau.',
        ),
        findsOneWidget,
      );

      await tester.enterText(find.byType(TextField), 'ab');
      await tester.pump();
      expect(find.text('2 / 500'), findsOneWidget);
      final bouton = find.ancestor(
        of: find.text('Confirmer le refus'),
        matching: find.byType(FilledButton),
      );
      expect(tester.widget<FilledButton>(bouton).onPressed, isNull);

      await tester.enterText(find.byType(TextField), 'Absent ce jour-là');
      await tester.pump();
      expect(tester.widget<FilledButton>(bouton).onPressed, isNotNull);
    });

    testWidgets('espaces seuls : refus impossible', (tester) async {
      await ouvrirRefus(tester);
      await tester.enterText(find.byType(TextField), '      ');
      await tester.pump();
      final bouton = find.ancestor(
        of: find.text('Confirmer le refus'),
        matching: find.byType(FilledButton),
      );
      expect(tester.widget<FilledButton>(bouton).onPressed, isNull);
    });

    testWidgets('longueur limitée à 500 caractères', (tester) async {
      await ouvrirRefus(tester);
      await tester.enterText(find.byType(TextField), 'a' * 600);
      await tester.pump();
      expect(find.text('500 / 500'), findsOneWidget);
    });

    testWidgets('confirmer envoie le motif, relit et revient', (tester) async {
      final serveur = await ouvrirRefus(
        tester,
        enPlus: {
          'POST /prestataire/reservations/r-1/refuser': Simulee(
            200,
            demandeApi(
              statut: 'refusee',
              avecMontants: false,
              motifRefus: 'Absent ce jour-là',
            ),
          ),
        },
      );
      await tester.enterText(find.byType(TextField), '  Absent ce jour-là  ');
      await tester.pump();
      await tester.tap(find.text('Confirmer le refus'));
      await passerTransition(tester);

      final envoi = serveur.requetes.singleWhere(
        (r) => r.method == 'POST' && r.path.endsWith('/refuser'),
      );
      expect(envoi.data, {'motif': 'Absent ce jour-là'});
      expect(cheminCourant(tester), isNot(Chemins.refusReservation('r-1')));
      expect(find.text('Demande refusée.'), findsOneWidget);
      final lectures = serveur.requetes.where(
        (r) => r.method == 'GET' && r.path == '/prestataire/reservations',
      );
      expect(lectures.length, greaterThanOrEqualTo(1));
    });

    testWidgets('refus rejeté par le serveur : message affiché, on reste', (
      tester,
    ) async {
      await ouvrirRefus(
        tester,
        enPlus: {
          'POST /prestataire/reservations/r-1/refuser': const Simulee(409, {
            'message': 'Cette demande a déjà été traitée.',
          }),
        },
      );
      await tester.enterText(find.byType(TextField), 'Absent ce jour-là');
      await tester.pump();
      await tester.tap(find.text('Confirmer le refus'));
      await passerTransition(tester);
      expect(find.text('Cette demande a déjà été traitée.'), findsOneWidget);
      expect(cheminCourant(tester), Chemins.refusReservation('r-1'));
    });
  });
}
