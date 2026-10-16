import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/animation/chargement.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_listes.dart';
import 'aide_reservation.dart';

final _debut = DateTime.utc(2026, 10, 24, 10);

Map<String, Object?> _res(
  String id, {
  String statut = 'en_attente',
  String prestataire = 'Salon Fatou',
  bool peutAnnuler = false,
  bool peutPayer = false,
  bool peutNoter = false,
  Map<String, Object?>? paiement,
  Map<String, Object?>? avis,
  String? motif,
}) => reservationApi(
  id,
  debut: _debut,
  statut: statut,
  prestataire: prestataire,
  peutAnnuler: peutAnnuler,
  peutPayer: peutPayer,
  peutNoter: peutNoter,
  paiement: paiement,
  avis: avis,
  motifRefus: motif,
);

final _aVenir = pageReservationsApi([
  _res(
    'r-1',
    statut: 'confirmee',
    peutAnnuler: true,
    paiement: {'statut': 'reussi', 'montantAcompte': 1500},
  ),
  _res('r-2', statut: 'acceptee', prestataire: 'Garage KM', peutPayer: true),
  _res('r-3', prestataire: 'Pressing Express'),
]);

AdaptateurListes _serveur({
  Map<String, Simulee> pages = const {},
  bool retenir = false,
}) => AdaptateurListes(
  routesClient({
    'GET /prestataires/p-1': Simulee(200, profilApi()),
    'GET /notifications/non-lues/compteur': const Simulee(200, {'nonLues': 0}),
    'GET /prestataires': Simulee(200, pagePrestataires([])),
  }),
  {'a_venir:1': Simulee(200, _aVenir), ...pages},
  retenir: retenir,
);

Future<void> _ouvrir(WidgetTester tester, AdaptateurListes serveur) async {
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: Chemins.reservations,
  );
  await passerTransition(tester);
}

void main() {
  setUpAll(initialiserFormats);

  group('Mes réservations', () {
    testWidgets('trois onglets et titre', (tester) async {
      await _ouvrir(tester, _serveur());
      expect(find.text('Mes'), findsOneWidget);
      expect(find.text('réservations'), findsOneWidget);
      expect(find.text('À venir'), findsOneWidget);
      expect(find.text('Terminées'), findsOneWidget);
      expect(find.text('Annulées'), findsOneWidget);
    });

    testWidgets('À venir : une carte par statut avec la bonne action', (
      tester,
    ) async {
      final serveur = _serveur();
      await _ouvrir(tester, serveur);
      expect(
        serveur.requetes.any(
          (r) =>
              r.path == '/reservations' &&
              r.queryParameters['groupe'] == 'a_venir',
        ),
        isTrue,
      );
      expect(find.text('Salon Fatou'), findsOneWidget);
      expect(find.text('Confirmée'), findsOneWidget);
      expect(
        find.text('Coupe femme · ${formaterDate(_debut)}'),
        findsNWidgets(3),
      );
      expect(find.textContaining('Acompte payé'), findsOneWidget);
      expect(find.text('Annuler'), findsOneWidget);

      expect(find.text('Acceptée'), findsOneWidget);
      expect(find.textContaining('à payer'), findsOneWidget);
      // L'acompte d'une réservation acceptée vient du profil du prestataire (API), pas d'un calcul.
      expect(find.textContaining(formaterArgent(1500)), findsNWidgets(2));
      expect(find.text('Payer'), findsOneWidget);

      expect(find.text('En attente'), findsOneWidget);
      expect(
        find.text('Le prestataire doit répondre à votre demande.'),
        findsOneWidget,
      );
    });

    testWidgets('sans peutAnnuler, pas de bouton « Annuler »', (tester) async {
      final serveur = _serveur(
        pages: {
          'a_venir:1': Simulee(
            200,
            pageReservationsApi([
              _res(
                'r-1',
                statut: 'confirmee',
                paiement: {'statut': 'reussi', 'montantAcompte': 1500},
              ),
            ]),
          ),
        },
      );
      await _ouvrir(tester, serveur);
      expect(find.text('Annuler'), findsNothing);
    });

    testWidgets('Terminées : avis à laisser ou note donnée', (tester) async {
      final serveur = _serveur(
        pages: {
          'terminees:1': Simulee(
            200,
            pageReservationsApi([
              _res('r-4', statut: 'terminee', peutNoter: true),
              _res(
                'r-5',
                statut: 'terminee',
                prestataire: 'Garage KM',
                avis: {'note': 4, 'commentaire': 'Bien'},
              ),
            ]),
          ),
        },
      );
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Terminées'));
      await passerTransition(tester);
      expect(find.text('Laisser un avis'), findsOneWidget);
      expect(find.text('Votre avis : 4/5'), findsOneWidget);
      expect(find.text('Terminée'), findsNWidgets(2));
    });

    testWidgets('Annulées : motif de refus affiché', (tester) async {
      final serveur = _serveur(
        pages: {
          'annulees:1': Simulee(
            200,
            pageReservationsApi([
              _res('r-6', statut: 'refusee', motif: 'Je suis indisponible.'),
              _res('r-7', statut: 'annulee'),
            ]),
          ),
        },
      );
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Annulées'));
      await passerTransition(tester);
      expect(find.text('Refusée'), findsOneWidget);
      expect(find.text('Annulée'), findsOneWidget);
      expect(find.text('Je suis indisponible.'), findsOneWidget);
    });

    testWidgets('état vide', (tester) async {
      final serveur = _serveur(
        pages: {'terminees:1': Simulee(200, pageReservationsApi([]))},
      );
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Terminées'));
      await passerTransition(tester);
      expect(find.text('Aucune réservation ici.'), findsOneWidget);
    });

    testWidgets('erreur : message et « Réessayer »', (tester) async {
      final serveur = _serveur(
        pages: {
          'annulees:1': const Simulee(500, {'message': 'Erreur serveur.'}),
        },
      );
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Annulées'));
      await passerTransition(tester);
      expect(find.text('Réessayer'), findsOneWidget);
    });

    testWidgets('chargement : squelettes', (tester) async {
      final serveur = _serveur(retenir: true);
      await lancerAcces(
        tester,
        serveur,
        jeton: 'jeton',
        chemin: Chemins.reservations,
      );
      await avancer(tester, 100);
      expect(find.byType(Squelette), findsWidgets);
      serveur.liberer();
      await passerTransition(tester);
    });

    testWidgets('pagination : la page suivante se charge au défilement', (
      tester,
    ) async {
      final page1 = [
        for (var i = 0; i < 8; i++)
          _res('a-$i', statut: 'confirmee', prestataire: 'Prestataire $i'),
      ];
      final serveur = _serveur(
        pages: {
          'a_venir:1': Simulee(200, pageReservationsApi(page1, derniere: 2)),
          'a_venir:2': Simulee(
            200,
            pageReservationsApi(
              [_res('b-1', statut: 'confirmee', prestataire: 'Dernier')],
              page: 2,
              derniere: 2,
            ),
          ),
        },
      );
      await _ouvrir(tester, serveur);
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -3000));
      await passerTransition(tester);
      expect(
        serveur.requetes.any(
          (r) => r.path == '/reservations' && r.queryParameters['page'] == 2,
        ),
        isTrue,
      );
      expect(find.text('Dernier'), findsOneWidget);
    });

    testWidgets('toucher une carte ouvre le détail', (tester) async {
      await _ouvrir(tester, _serveur());
      await toucher(tester, find.text('Pressing Express'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.detailReservation('r-3'));
    });

    testWidgets('« Payer » ouvre le paiement', (tester) async {
      await _ouvrir(tester, _serveur());
      await toucher(tester, find.text('Payer'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.paiement('r-2'));
    });

    testWidgets('« Annuler » ouvre la feuille de confirmation', (tester) async {
      final serveur = _serveur();
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('Annuler'));
      await passerTransition(tester);
      expect(find.text('Annuler cette réservation ?'), findsOneWidget);
      expect(find.text('Garder ma réservation'), findsOneWidget);
      expect(serveur.requetes.where((r) => r.method == 'POST'), isEmpty);
    });

    testWidgets('petit écran 320×568 : pas de débordement', (tester) async {
      tester.view.physicalSize = const Size(320, 568);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await _ouvrir(tester, _serveur());
      expect(tester.takeException(), isNull);
    });
  });
}
