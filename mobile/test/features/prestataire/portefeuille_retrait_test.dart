import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_prestataire.dart';

Map<String, Object?> _mouvement({
  String id = 'm-1',
  String type = 'credit',
  num montant = 1000,
  String? prestation = 'Coupe femme',
  String? operateur,
  String? statutRetrait,
  String libelle = 'Prestation terminée (acompte moins commission)',
  String date = '2026-10-22T08:50:00+00:00',
}) => {
  'id': id,
  'type': type,
  'montant': montant,
  'libelle': libelle,
  'soldeApres': 8000,
  'prestation': prestation,
  'operateur': operateur,
  'statutRetrait': statutRetrait,
  'date': date,
};

Map<String, Object?> _page(
  List<Map<String, Object?>> elements, {
  int page = 1,
  int derniere = 1,
}) => {
  'data': elements,
  'meta': {
    'current_page': page,
    'last_page': derniere,
    'per_page': 20,
    'total': elements.length,
  },
};

Map<String, Simulee> _routes({
  List<Map<String, Object?>>? mouvements,
  Map<String, Object?>? solde,
  Map<String, Simulee> enPlus = const {},
}) => {
  'GET /me': Simulee(200, utilisateurPrestataire()),
  'GET /prestataire/solde': Simulee(
    200,
    solde ??
        {
          'solde': 42500,
          'montantMinimumRetrait': 1000,
          'montantMaximumRetrait': 500000,
        },
  ),
  'GET /prestataire/portefeuille/mouvements': Simulee(
    200,
    _page(
      mouvements ??
          [
            _mouvement(),
            _mouvement(
              id: 'm-2',
              type: 'retrait',
              montant: 20000,
              prestation: null,
              operateur: 'wave',
              statutRetrait: 'reussi',
              libelle: 'Retrait',
              date: '2026-10-20T08:00:00+00:00',
            ),
          ],
    ),
  ),
  'GET /prestataire/reservations': Simulee(200, pageDemandesApi([])),
  'GET /notifications/non-lues/compteur': const Simulee(200, {'nonLues': 0}),
  ...enPlus,
};

void _vue390(WidgetTester tester) {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Future<void> _ouvrir(
  WidgetTester tester,
  AdaptateurSimule serveur,
  String chemin,
) async {
  _vue390(tester);
  await lancerAcces(tester, serveur, jeton: 'jeton', chemin: chemin);
  await passerTransition(tester);
}

Future<void> _remplir(WidgetTester tester, String montant, String tel) async {
  await saisir(tester, 0, montant);
  await saisir(tester, 1, tel);
}

Finder get _boutonEnvoyer => find.textContaining('Retirer ');

void main() {
  setUpAll(initialiserFormats);

  group('Portefeuille', () {
    testWidgets('affiche le solde, les limites du serveur et les mouvements', (
      tester,
    ) async {
      await _ouvrir(tester, AdaptateurSimule(_routes()), Chemins.portefeuille);
      expect(find.text('Solde disponible'), findsOneWidget);
      expect(find.textContaining('42'), findsWidgets);
      expect(find.textContaining('Retrait de 1'), findsOneWidget);
      expect(find.text('Prestation terminée'), findsOneWidget);
      expect(find.textContaining('Coupe femme'), findsOneWidget);
      expect(find.text('Retrait Wave'), findsOneWidget);
      expect(find.textContaining('Effectué'), findsOneWidget);
    });

    testWidgets('un retrait en cours et un échec sont libellés', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(
        _routes(
          mouvements: [
            _mouvement(
              type: 'retrait',
              prestation: null,
              operateur: 'orange_money',
              statutRetrait: 'en_cours',
            ),
            _mouvement(
              id: 'm-2',
              type: 'retrait',
              prestation: null,
              operateur: 'wave',
              statutRetrait: 'echoue',
            ),
          ],
        ),
      );
      await _ouvrir(tester, serveur, Chemins.portefeuille);
      expect(find.text('Retrait Orange Money'), findsOneWidget);
      expect(find.textContaining('En cours'), findsOneWidget);
      expect(find.textContaining('Échoué'), findsOneWidget);
    });

    testWidgets('tolère des champs facultatifs absents', (tester) async {
      final serveur = AdaptateurSimule(
        _routes(
          solde: {'solde': 100},
          mouvements: [
            {
              'id': 'm-9',
              'type': 'retrait',
              'montant': 500,
              'date': '2026-10-22T08:50:00+00:00',
            },
          ],
        ),
      );
      await _ouvrir(tester, serveur, Chemins.portefeuille);
      expect(find.text('Retrait'), findsOneWidget);
      expect(find.textContaining('Retrait de'), findsNothing);
    });

    testWidgets('historique vide', (tester) async {
      await _ouvrir(
        tester,
        AdaptateurSimule(_routes(mouvements: [])),
        Chemins.portefeuille,
      );
      expect(find.text('Aucun mouvement pour le moment.'), findsOneWidget);
    });

    testWidgets('pagination : la page suivante est demandée au défilement', (
      tester,
    ) async {
      final serveur = AdaptateurSimule({
        ..._routes(),
        'GET /prestataire/portefeuille/mouvements': Simulee(
          200,
          _page([
            for (var i = 0; i < 12; i++) _mouvement(id: 'm-$i'),
          ], derniere: 2),
        ),
      });
      await _ouvrir(tester, serveur, Chemins.portefeuille);
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -3000));
      await avancer(tester, 300);
      final pages = serveur.requetes
          .where((r) => r.path == '/prestataire/portefeuille/mouvements')
          .map((r) => r.queryParameters['page'])
          .toList();
      expect(pages, containsAll([1, 2]));
    });

    testWidgets('erreur serveur : message et réessai', (tester) async {
      final serveur = AdaptateurSimule({
        ..._routes(),
        'GET /prestataire/portefeuille/mouvements': const Simulee(500, {
          'message': 'Erreur du serveur.',
        }),
      });
      await _ouvrir(tester, serveur, Chemins.portefeuille);
      expect(find.text('Réessayer'), findsOneWidget);
    });

    testWidgets('« Retirer » ouvre l’écran de retrait', (tester) async {
      await _ouvrir(tester, AdaptateurSimule(_routes()), Chemins.portefeuille);
      await tester.tap(find.byKey(const ValueKey('bouton-retirer')));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.retrait);
    });
  });

  group('Retrait', () {
    testWidgets('affiche le solde et les limites du serveur', (tester) async {
      await _ouvrir(tester, AdaptateurSimule(_routes()), Chemins.retrait);
      expect(find.textContaining('Solde disponible'), findsOneWidget);
      expect(find.textContaining('Minimum'), findsOneWidget);
      expect(find.text('Wave'), findsOneWidget);
      expect(find.text('Orange Money'), findsOneWidget);
    });

    testWidgets('succès : envoie le montant, le numéro, l’opérateur', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(
        _routes(
          enPlus: {
            'POST /prestataire/retraits': const Simulee(201, {
              'id': 'ret-1',
              'montant': 20000,
              'statut': 'reussi',
              'operateur': 'orange_money',
              'telephoneVersement': '+221******567',
            }),
          },
        ),
      );
      await _ouvrir(tester, serveur, Chemins.retrait);
      await tester.tap(find.byKey(const ValueKey('operateur-orange_money')));
      await tester.pump();
      await _remplir(tester, '20000', '77 123 45 67');
      expect(find.textContaining('20'), findsWidgets);
      await tester.tap(_boutonEnvoyer);
      await avancer(tester, 300);

      final envoi = serveur.requetes.singleWhere(
        (r) => r.method == 'POST' && r.path == '/prestataire/retraits',
      );
      expect(envoi.data, {
        'montant': 20000,
        'telephone_versement': '771234567',
        'operateur': 'orange_money',
      });
      expect(find.byKey(const ValueKey('montant-retrait')), findsOneWidget);
      expect(find.textContaining('+221******567'), findsOneWidget);
      expect(find.text('Le versement a été effectué.'), findsOneWidget);
    });

    testWidgets('retrait accepté mais en cours', (tester) async {
      final serveur = AdaptateurSimule(
        _routes(
          enPlus: {
            'POST /prestataire/retraits': const Simulee(201, {
              'id': 'ret-1',
              'montant': 5000,
              'statut': 'en_cours',
              'operateur': 'wave',
              'telephoneVersement': '+221******567',
            }),
          },
        ),
      );
      await _ouvrir(tester, serveur, Chemins.retrait);
      await _remplir(tester, '5000', '771234567');
      await tester.tap(_boutonEnvoyer);
      await avancer(tester, 300);
      expect(find.textContaining('prise en compte'), findsOneWidget);
      await tester.tap(find.text('Retour au portefeuille'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.portefeuille);
    });

    testWidgets('échec 503 : le message du serveur s’affiche', (tester) async {
      final serveur = AdaptateurSimule(
        _routes(
          enPlus: {
            'POST /prestataire/retraits': const Simulee(503, {
              'message': 'Le versement a été refusé. Votre solde est intact.',
            }),
          },
        ),
      );
      await _ouvrir(tester, serveur, Chemins.retrait);
      await _remplir(tester, '5000', '771234567');
      await tester.tap(_boutonEnvoyer);
      await avancer(tester, 300);
      expect(
        find.text('Le versement a été refusé. Votre solde est intact.'),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.retrait);
    });

    testWidgets('422 : le message du serveur s’affiche sous le champ', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(
        _routes(
          enPlus: {
            'POST /prestataire/retraits': const Simulee(422, {
              'message': 'Données invalides.',
              'errors': {
                'montant': ['Le montant dépasse votre solde.'],
              },
            }),
          },
        ),
      );
      await _ouvrir(tester, serveur, Chemins.retrait);
      await _remplir(tester, '900000', '771234567');
      await tester.tap(_boutonEnvoyer);
      await avancer(tester, 300);
      expect(find.text('Le montant dépasse votre solde.'), findsOneWidget);
    });

    testWidgets('champs vides : rien n’est envoyé', (tester) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur, Chemins.retrait);
      await tester.tap(find.text('Retirer').last);
      await avancer(tester, 200);
      expect(find.text('Indiquez le montant à retirer.'), findsOneWidget);
      expect(find.text('Indiquez votre numéro.'), findsOneWidget);
      expect(serveur.requetes.where((r) => r.method == 'POST'), isEmpty);
    });

    testWidgets('double clic : une seule demande part', (tester) async {
      final serveur = AdaptateurSimule(
        _routes(
          enPlus: {
            'POST /prestataire/retraits': const Simulee(201, {
              'id': 'ret-1',
              'montant': 5000,
              'statut': 'en_cours',
              'operateur': 'wave',
              'telephoneVersement': '+221******567',
            }),
          },
        ),
      );
      await _ouvrir(tester, serveur, Chemins.retrait);
      await _remplir(tester, '5000', '771234567');
      final bouton = _boutonEnvoyer;
      await tester.tap(bouton);
      await tester.tap(bouton, warnIfMissed: false);
      await avancer(tester, 300);
      expect(
        serveur.requetes.where(
          (r) => r.method == 'POST' && r.path == '/prestataire/retraits',
        ),
        hasLength(1),
      );
    });
  });
}
