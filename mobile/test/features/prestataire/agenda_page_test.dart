import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/animation/chargement.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/reservation/depot_reservation.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';
import 'aide_prestataire.dart';

/// Vendredi 23 octobre 2026, comme la maquette : la bande montre Ven. 23 à Mar. 27.
DateTime _maintenant() => DateTime.utc(2026, 10, 23, 9);

void _vue390(WidgetTester tester) {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

/// Faux serveur qui sert `?groupe=a_venir` page par page (20 lignes par page, [pages] pages au total).
class _AdaptateurAgenda extends AdaptateurSimule {
  _AdaptateurAgenda(super.routes, {this.pages = 1});

  final int pages;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    if (options.method == 'GET' &&
        options.path == '/prestataire/reservations') {
      requetes.add(options);
      final page = (options.queryParameters['page'] as int?) ?? 1;
      final corps = jsonEncode(
        pageDemandesApi(
          [
            for (var i = 0; i < 20; i++)
              demandeApi(
                id: 'p$page-$i',
                statut: 'confirmee',
                prenom: 'Client$page',
                initiale: 'X',
                debut:
                    '2026-10-24T${(8 + i % 10).toString().padLeft(2, '0')}:00:00+00:00',
              ),
          ],
          page: page,
          derniere: pages,
          total: pages * 20,
        ),
      );
      return ResponseBody.fromString(
        corps,
        200,
        headers: {
          Headers.contentTypeHeader: [Headers.jsonContentType],
        },
      );
    }
    return super.fetch(options, requestStream, cancelFuture);
  }
}

Map<String, Simulee> _routes([List<Map<String, Object?>>? demandes]) => {
  'GET /me': Simulee(200, utilisateurPrestataire()),
  'GET /prestataire/solde': const Simulee(200, {'solde': 42500}),
  'GET /prestataire/reservations': Simulee(
    200,
    pageDemandesApi(demandes ?? []),
  ),
  'GET /notifications/non-lues/compteur': const Simulee(200, {'nonLues': 0}),
};

Future<void> _ouvrir(WidgetTester tester, AdaptateurSimule serveur) async {
  _vue390(tester);
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: Chemins.agenda,
    surcharges: [horlogeProvider.overrideWithValue(_maintenant)],
  );
  await passerTransition(tester);
}

void main() {
  setUpAll(initialiserFormats);

  group('Agenda du prestataire', () {
    testWidgets('bande de 5 jours, premier jour avec rendez-vous, statuts', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(
        _routes([
          demandeApi(
            id: 'r-2',
            statut: 'acceptee',
            prenom: 'Mariama',
            initiale: 'B',
            prestation: 'Brushing',
            prix: 3000,
            duree: 45,
            debut: '2026-10-24T16:00:00+00:00',
          ),
          demandeApi(statut: 'confirmee'),
          demandeApi(
            id: 'r-3',
            statut: 'en_attente',
            prenom: 'Khadija',
            initiale: 'S',
            prestation: 'Tresses',
            prix: 12000,
            duree: 180,
            debut: '2026-10-24T12:00:00+00:00',
          ),
        ]),
      );
      await _ouvrir(tester, serveur);

      expect(cheminCourant(tester), Chemins.agenda);
      expect(find.text('Mon'), findsOneWidget);
      expect(find.text('agenda'), findsOneWidget);
      for (final (nom, numero) in [
        ('Ven.', '23'),
        ('Sam.', '24'),
        ('Dim.', '25'),
        ('Lun.', '26'),
        ('Mar.', '27'),
      ]) {
        expect(find.bySemanticsLabel('$nom $numero'), findsOneWidget);
      }
      expect(find.text('Samedi 24 octobre · 3 rendez-vous'), findsOneWidget);
      // Triés par heure, avec leur durée.
      final h10 = tester.getTopLeft(find.text('10h00')).dy;
      final h12 = tester.getTopLeft(find.text('12h00')).dy;
      final h16 = tester.getTopLeft(find.text('16h00')).dy;
      expect(h10, lessThan(h12));
      expect(h12, lessThan(h16));
      expect(find.text('1 h'), findsOneWidget);
      expect(find.text('3 h'), findsOneWidget);
      expect(find.text('45 min'), findsOneWidget);
      expect(
        find.text('Coupe femme · ${formaterArgent(5000)}'),
        findsOneWidget,
      );
      expect(find.text('Confirmée'), findsOneWidget);
      expect(find.text('En attente de paiement'), findsOneWidget);
      expect(find.text('À traiter'), findsOneWidget);

      final requete = serveur.requetes.firstWhere(
        (r) => r.path == '/prestataire/reservations',
      );
      expect(requete.queryParameters['groupe'], 'a_venir');
    });

    testWidgets('un jour sans rendez-vous affiche « Aucun rendez-vous »', (
      tester,
    ) async {
      await _ouvrir(tester, AdaptateurSimule(_routes([demandeApi()])));
      await tester.tap(find.bySemanticsLabel('Dim. 25'));
      await avancer(tester, 300);
      expect(
        find.text('Dimanche 25 octobre · Aucun rendez-vous'),
        findsOneWidget,
      );
      expect(find.text('Aminata D.'), findsNothing);

      await tester.tap(find.bySemanticsLabel('Sam. 24'));
      await avancer(tester, 300);
      expect(find.text('Samedi 24 octobre · 1 rendez-vous'), findsOneWidget);
      expect(find.text('Aminata D.'), findsOneWidget);
    });

    testWidgets(
      'aucun rendez-vous du tout : le jour sélectionné est aujourd\'hui',
      (tester) async {
        await _ouvrir(tester, AdaptateurSimule(_routes()));
        expect(
          find.text('Vendredi 23 octobre · Aucun rendez-vous'),
          findsOneWidget,
        );
      },
    );

    testWidgets('toucher un rendez-vous ouvre le détail', (tester) async {
      await _ouvrir(
        tester,
        AdaptateurSimule(_routes([demandeApi(statut: 'confirmee')])),
      );
      await tester.tap(find.text('Aminata D.'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.reservationPrestataire('r-1'));
    });

    testWidgets('chargement : squelettes, puis contenu', (tester) async {
      _vue390(tester);
      final serveur = AdaptateurLentPour(_routes([demandeApi()]), {
        'GET /prestataire/reservations',
      });
      await lancerAcces(
        tester,
        serveur,
        jeton: 'jeton',
        chemin: Chemins.agenda,
        surcharges: [horlogeProvider.overrideWithValue(_maintenant)],
      );
      await avancer(tester, 100);
      expect(find.byType(Squelette), findsWidgets);
      serveur.liberer();
      await passerTransition(tester);
      expect(find.text('Aminata D.'), findsOneWidget);
    });

    testWidgets('erreur : message et Réessayer', (tester) async {
      final routes = _routes()
        ..['GET /prestataire/reservations'] = const Simulee(503, {
          'message': 'Service indisponible.',
        });
      await _ouvrir(tester, AdaptateurSimule(routes));
      expect(find.text('Service indisponible.'), findsOneWidget);
      expect(find.text('Réessayer'), findsOneWidget);
    });

    testWidgets('charge 5 pages d\'office puis propose « Charger plus »', (
      tester,
    ) async {
      final serveur = _AdaptateurAgenda(_routes(), pages: 8);
      await _ouvrir(tester, serveur);
      await passerTransition(tester);

      List<Object?> pagesDemandees() => serveur.requetes
          .where((r) => r.path == '/prestataire/reservations')
          .map((r) => r.queryParameters['page'])
          .toList();
      expect(pagesDemandees(), [1, 2, 3, 4, 5]);

      await tester.ensureVisible(find.text('Charger plus'));
      await tester.pump();
      await tester.tap(find.text('Charger plus'));
      await passerTransition(tester);
      expect(pagesDemandees(), [1, 2, 3, 4, 5, 6]);
    });

    testWidgets('moins de 5 pages : tout est chargé, pas de « Charger plus »', (
      tester,
    ) async {
      final serveur = _AdaptateurAgenda(_routes(), pages: 3);
      await _ouvrir(tester, serveur);
      await passerTransition(tester);
      expect(
        serveur.requetes.where((r) => r.path == '/prestataire/reservations'),
        hasLength(3),
      );
      expect(find.text('Charger plus'), findsNothing);
    });
  });
}
