import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';

Map<String, Object?> _litige(
  String id, {
  String statut = 'ouvert',
  String motif = 'Prestataire absent',
  List<Map<String, Object?>> preuves = const [],
  String? reponse,
  String? decision,
  String? motifDecision,
}) => {
  'id': id,
  'statut': statut,
  'motif': motif,
  'description': 'Il ne est pas venu au rendez-vous.',
  'dateCreation': '2026-10-25T09:00:00+00:00',
  'nombrePreuves': preuves.length,
  'preuves': preuves,
  'reservation': {
    'prestation': 'Coupe femme',
    'dateDebut': '2026-10-24T10:00:00+00:00',
  },
  'prestataire': {'nomEntreprise': 'Salon Fatou'},
  'reponsePrestataire': reponse,
  'decision': decision,
  'motifDecision': motifDecision,
};

Map<String, Object?> _page(List<Object?> data, int page, int derniere) => {
  'data': data,
  'meta': {'current_page': page, 'last_page': derniere},
};

Future<AdaptateurSimule> _ouvrir(
  WidgetTester tester,
  String chemin,
  Map<String, Simulee> routes,
) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  final serveur = AdaptateurSimule(routesClient(routes));
  await lancerAcces(tester, serveur, jeton: 'jeton', chemin: chemin);
  await passerTransition(tester);
  return serveur;
}

void main() {
  setUpAll(initialiserFormats);

  testWidgets('liste : cartes, statut, puis détail au toucher', (tester) async {
    await _ouvrir(tester, Chemins.signalements, {
      'GET /litiges': Simulee(
        200,
        _page(
          [_litige('l-1'), _litige('l-2', statut: 'cloture', motif: 'Autre')],
          1,
          1,
        ),
      ),
      'GET /litiges/l-1': Simulee(200, {'data': _litige('l-1')}),
    });
    expect(find.text('Prestataire absent'), findsOneWidget);
    expect(find.text('Ouvert'), findsOneWidget);
    expect(find.text('Clôturé'), findsOneWidget);
    expect(
      find.textContaining('Salon Fatou · Coupe femme · '),
      findsNWidgets(2),
    );
    await tester.tap(find.text('Prestataire absent'));
    await tester.pump();
    await passerTransition(tester);
    expect(find.text('Il ne est pas venu au rendez-vous.'), findsOneWidget);
  });

  testWidgets('liste vide : message', (tester) async {
    await _ouvrir(tester, Chemins.signalements, {
      'GET /litiges': Simulee(200, _page([], 1, 1)),
    });
    expect(find.text("Vous n'avez fait aucun signalement."), findsOneWidget);
  });

  testWidgets('liste : erreur serveur avec bouton Réessayer', (tester) async {
    await _ouvrir(tester, Chemins.signalements, {
      'GET /litiges': const Simulee(503, {'message': 'Service indisponible.'}),
    });
    expect(find.text('Service indisponible.'), findsOneWidget);
    expect(find.text('Réessayer'), findsOneWidget);
  });

  testWidgets('liste : la page suivante se charge en fin de défilement', (
    tester,
  ) async {
    final serveur = await _ouvrir(tester, Chemins.signalements, {
      'GET /litiges': Simulee(
        200,
        _page([for (var i = 0; i < 20; i++) _litige('l-$i')], 1, 2),
      ),
    });
    await tester.drag(find.byType(CustomScrollView), const Offset(0, -6000));
    await tester.pump();
    await passerTransition(tester);
    final pages = serveur.requetes
        .where((r) => r.path == '/litiges')
        .map((r) => r.queryParameters['page'])
        .toList();
    expect(pages, containsAll([1, 2]));
  });

  testWidgets('détail clôturé : réponse, décision et motif de décision', (
    tester,
  ) async {
    await _ouvrir(tester, Chemins.signalement('l-1'), {
      'GET /litiges/l-1': Simulee(200, {
        'data': _litige(
          'l-1',
          statut: 'cloture',
          reponse: 'Je me suis présenté à 10 h.',
          decision: 'avertissement',
          motifDecision: 'Retard constaté.',
        ),
      }),
    });
    expect(find.text('Clôturé'), findsOneWidget);
    expect(find.text('Réponse du prestataire'), findsOneWidget);
    expect(find.text('Je me suis présenté à 10 h.'), findsOneWidget);
    expect(find.text('Avertissement envoyé au prestataire'), findsOneWidget);
    expect(find.text('Retard constaté.'), findsOneWidget);
  });

  testWidgets('détail : les preuves sont lues avec le jeton', (tester) async {
    final png = base64Decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
    );
    final serveur = await _ouvrir(tester, Chemins.signalement('l-1'), {
      'GET /litiges/l-1': Simulee(200, {
        'data': _litige(
          'l-1',
          preuves: [
            {'id': 'p-1', 'url': '/api/v1/litiges/l-1/preuves/p-1'},
          ],
        ),
      }),
      'GET /litiges/l-1/preuves/p-1': Simulee(200, png),
    });
    final lecture = serveur.requetes.where(
      (r) => r.path == '/litiges/l-1/preuves/p-1',
    );
    expect(lecture, hasLength(1));
    expect(lecture.single.headers['Authorization'], 'Bearer jeton');
  });
}
