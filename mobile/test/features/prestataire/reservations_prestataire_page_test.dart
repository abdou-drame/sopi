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

final _surcharges = [horlogeProvider.overrideWithValue(_maintenant)];

void _vue390(WidgetTester tester) {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Map<String, Simulee> _routes(
  Simulee reservations, {
  Map<String, Simulee> enPlus = const {},
}) => {
  'GET /me': Simulee(200, utilisateurPrestataire()),
  'GET /prestataire/solde': const Simulee(200, {'solde': 42500}),
  'GET /prestataire/reservations': reservations,
  'GET /notifications/non-lues/compteur': const Simulee(200, {'nonLues': 0}),
  ...enPlus,
};

Future<AdaptateurSimule> _ouvrir(
  WidgetTester tester,
  Simulee reservations, {
  Map<String, Simulee> enPlus = const {},
}) async {
  _vue390(tester);
  final serveur = AdaptateurSimule(_routes(reservations, enPlus: enPlus));
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: Chemins.reservationsPrestataire,
    surcharges: _surcharges,
  );
  return serveur;
}

void main() {
  setUpAll(initialiserFormats);

  group('formaterReste', () {
    test('heures et minutes sur deux chiffres', () {
      expect(
        formaterReste(const Duration(hours: 11, minutes: 20)),
        'Il reste 11 h 20',
      );
      expect(
        formaterReste(const Duration(hours: 1, minutes: 5)),
        'Il reste 1 h 05',
      );
    });

    test('sous une heure : minutes, arrondies au-dessus', () {
      expect(formaterReste(const Duration(minutes: 45)), 'Il reste 45 min');
      expect(formaterReste(const Duration(seconds: 10)), 'Il reste 1 min');
    });

    test('à zéro ou après : délai dépassé', () {
      expect(formaterReste(Duration.zero), 'Délai dépassé');
      expect(formaterReste(const Duration(minutes: -3)), 'Délai dépassé');
    });
  });

  group('Réservations du prestataire', () {
    testWidgets('À traiter : cartes, pastille, « Il reste », boutons', (
      tester,
    ) async {
      final serveur = await _ouvrir(
        tester,
        Simulee(
          200,
          pageDemandesApi([
            demandeApi(),
            demandeApi(
              id: 'r-2',
              statut: 'acceptee',
              prenom: 'Mariama',
              initiale: 'B',
              prestation: 'Brushing',
              debut: '2026-10-26T09:00:00+00:00',
            ),
          ]),
        ),
      );
      await passerTransition(tester);

      expect(cheminCourant(tester), Chemins.reservationsPrestataire);
      expect(find.textContaining('réservations'), findsWidgets);
      for (final onglet in ['À traiter', 'À venir', 'Terminées', 'Annulées']) {
        expect(find.bySemanticsLabel(onglet), findsOneWidget);
      }
      expect(find.text('Aminata D.'), findsOneWidget);
      expect(find.text('En attente'), findsOneWidget);
      expect(find.text('Coupe femme · Sam. 24 oct., 10h00'), findsOneWidget);
      expect(find.text('Il reste 11 h 20'), findsOneWidget);
      expect(find.text('Accepter'), findsOneWidget);
      expect(find.text('Refuser'), findsOneWidget);
      expect(find.text('Mariama B.'), findsOneWidget);
      expect(find.text('Acceptée'), findsOneWidget);
      expect(
        find.text("En attente du paiement de l'acompte par le client."),
        findsOneWidget,
      );
      final requete = serveur.requetes.firstWhere(
        (r) => r.path == '/prestataire/reservations',
      );
      expect(requete.queryParameters['statut'], 'en_attente');
    });

    testWidgets('chaque onglet envoie son filtre', (tester) async {
      final serveur = await _ouvrir(tester, Simulee(200, pageDemandesApi([])));
      await passerTransition(tester);
      for (final (onglet, groupe) in [
        ('À venir', 'a_venir'),
        ('Terminées', 'terminees'),
        ('Annulées', 'annulees'),
      ]) {
        await tester.tap(find.bySemanticsLabel(onglet));
        await passerTransition(tester);
        final requete = serveur.requetes.lastWhere(
          (r) => r.path == '/prestataire/reservations',
        );
        expect(requete.queryParameters['groupe'], groupe);
        expect(requete.queryParameters.containsKey('statut'), isFalse);
        expect(find.text('Aucune réservation ici.'), findsOneWidget);
      }
    });

    testWidgets('liste vide sur À traiter', (tester) async {
      await _ouvrir(tester, Simulee(200, pageDemandesApi([])));
      await passerTransition(tester);
      expect(
        find.text('Aucune demande à traiter pour le moment.'),
        findsOneWidget,
      );
    });

    testWidgets('une demande terminée n\'a ni compte à rebours ni boutons', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        Simulee(200, pageDemandesApi([demandeApi(statut: 'terminee')])),
      );
      await passerTransition(tester);
      await tester.tap(find.bySemanticsLabel('Terminées'));
      await passerTransition(tester);
      expect(find.text('Terminée'), findsOneWidget);
      expect(find.textContaining('Il reste'), findsNothing);
      expect(find.text('Accepter'), findsNothing);
    });

    testWidgets('défilement : la page 2 est demandée', (tester) async {
      final premiere = [
        for (var i = 0; i < 20; i++) demandeApi(id: 'r-$i', prenom: 'Client$i'),
      ];
      final serveur = await _ouvrir(
        tester,
        Simulee(200, pageDemandesApi(premiere, derniere: 2, total: 21)),
      );
      await passerTransition(tester);
      await tester.drag(find.byType(CustomScrollView), const Offset(0, -6000));
      await passerTransition(tester);
      final pages = serveur.requetes
          .where((r) => r.path == '/prestataire/reservations')
          .map((r) => r.queryParameters['page'])
          .toList();
      expect(pages, containsAllInOrder([1, 2]));
    });

    testWidgets('erreur : message et bouton Réessayer', (tester) async {
      await _ouvrir(
        tester,
        const Simulee(503, {'message': 'Erreur du serveur.'}),
      );
      await passerTransition(tester);
      expect(find.text('Erreur du serveur.'), findsOneWidget);
      expect(find.text('Réessayer'), findsOneWidget);
    });

    testWidgets('chargement : squelettes', (tester) async {
      _vue390(tester);
      final serveur = AdaptateurLentPour(
        _routes(Simulee(200, pageDemandesApi([demandeApi()]))),
        {'GET /prestataire/reservations'},
      );
      await lancerAcces(
        tester,
        serveur,
        jeton: 'jeton',
        chemin: Chemins.reservationsPrestataire,
        surcharges: _surcharges,
      );
      await avancer(tester, 100);
      expect(find.byType(Squelette), findsWidgets);
      serveur.liberer();
      await passerTransition(tester);
      expect(find.text('Aminata D.'), findsOneWidget);
    });

    testWidgets('toucher une carte ouvre le détail', (tester) async {
      await _ouvrir(tester, Simulee(200, pageDemandesApi([demandeApi()])));
      await passerTransition(tester);
      await tester.tap(find.text('Aminata D.'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.reservationPrestataire('r-1'));
    });

    testWidgets('Accepter appelle l\'API puis relit la liste', (tester) async {
      final serveur = await _ouvrir(
        tester,
        Simulee(200, pageDemandesApi([demandeApi()])),
        enPlus: {
          'POST /prestataire/reservations/r-1/accepter': Simulee(
            200,
            demandeApi(statut: 'acceptee', avecMontants: false),
          ),
        },
      );
      await passerTransition(tester);
      await tester.tap(find.text('Accepter'));
      await passerTransition(tester);
      expect(
        serveur.requetes.where(
          (r) => r.method == 'POST' && r.path.endsWith('/accepter'),
        ),
        hasLength(1),
      );
      final lectures = serveur.requetes.where(
        (r) => r.method == 'GET' && r.path == '/prestataire/reservations',
      );
      expect(lectures.length, greaterThanOrEqualTo(2));
    });

    testWidgets('Accepter refusé par le serveur : son message s\'affiche', (
      tester,
    ) async {
      await _ouvrir(
        tester,
        Simulee(200, pageDemandesApi([demandeApi()])),
        enPlus: {
          'POST /prestataire/reservations/r-1/accepter': const Simulee(409, {
            'message': 'Cette demande a déjà été traitée.',
          }),
        },
      );
      await passerTransition(tester);
      await tester.tap(find.text('Accepter'));
      await passerTransition(tester);
      expect(find.text('Cette demande a déjà été traitée.'), findsOneWidget);
    });
  });
}
