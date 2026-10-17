import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_prestataire.dart';

Map<String, Object?> _avis(String id, {String auteur = 'Awa D.'}) => {
  'id': id,
  'note': 5,
  'commentaire': 'Très bien $id',
  'auteur': auteur,
  'dateCreation': '2026-10-10T08:00:00+00:00',
};

Map<String, Object?> _pageAvis(
  List<Map<String, Object?>> data, {
  int page = 1,
  int derniere = 1,
  double? note = 4.5,
}) => {
  'data': data,
  'meta': {
    'current_page': page,
    'last_page': derniere,
    'per_page': 20,
    'total': data.length,
  },
  'noteMoyenne': note,
  'nombreAvis': data.length,
};

Map<String, Object?> _litige(
  String id, {
  String statut = 'ouvert',
  String? reponse,
}) => {
  'id': id,
  'statut': statut,
  'motif': 'Prestataire absent',
  'description': 'Personne ne m’a ouvert.',
  'dateCreation': '2026-10-20T08:00:00+00:00',
  'reservation': {
    'id': 'r-1',
    'statut': 'terminee',
    'dateDebut': '2026-10-24T10:00:00+00:00',
    'prestation': 'Coupe femme',
  },
  'nombrePreuves': 0,
  'reponsePrestataire': reponse,
  'reponseLe': reponse == null ? null : '2026-10-21T08:00:00+00:00',
  'decision': null,
  'motifDecision': null,
  'clotureLe': null,
  'preuves': <Object?>[],
  'client': {'prenom': 'Aminata', 'initialeNom': 'D'},
};

Map<String, Object?> _pageLitiges(List<Map<String, Object?>> data) => {
  'data': data,
  'meta': {
    'current_page': 1,
    'last_page': 1,
    'per_page': 20,
    'total': data.length,
  },
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

Map<String, Simulee> _base(Map<String, Simulee> plus) => {
  'GET /me': Simulee(200, utilisateurPrestataire()),
  'GET /prestataire/reservations': Simulee(200, pageDemandesApi([])),
  'GET /notifications/non-lues/compteur': const Simulee(200, {'nonLues': 0}),
  ...plus,
};

Map<String, Simulee> _detail(
  Map<String, Object?> l, [
  Map<String, Simulee> plus = const {},
]) => _base({
  'GET /prestataire/litiges/${l['id']}': Simulee(200, l),
  'GET /prestataire/litiges': Simulee(200, _pageLitiges([l])),
  ...plus,
});

void main() {
  setUpAll(initialiserFormats);

  group('Avis reçus', () {
    testWidgets('synthèse et liste', (tester) async {
      final serveur = AdaptateurSimule(
        _base({
          'GET /prestataire/avis': Simulee(
            200,
            _pageAvis([_avis('a-1'), _avis('a-2', auteur: 'Moussa K.')]),
          ),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.avisRecus);
      expect(find.byKey(const ValueKey('synthese-avis')), findsOneWidget);
      expect(find.text('Note moyenne'), findsOneWidget);
      expect(find.text('Très bien a-1'), findsOneWidget);
      expect(find.text('Moussa K.'), findsOneWidget);
      expect(find.text("Voir plus d'avis"), findsNothing);
    });

    testWidgets('« Voir plus d’avis » charge la page suivante', (tester) async {
      final serveur = AdaptateurSimule(
        _base({
          'GET /prestataire/avis': Simulee(
            200,
            _pageAvis([_avis('a-1')], derniere: 2),
          ),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.avisRecus);
      expect(find.text("Voir plus d'avis"), findsOneWidget);
      expect(find.text('Très bien a-2'), findsNothing);
      serveur.routes['GET /prestataire/avis'] = Simulee(
        200,
        _pageAvis([_avis('a-2')], page: 2, derniere: 2),
      );
      await tester.ensureVisible(find.text("Voir plus d'avis"));
      await tester.tap(find.text("Voir plus d'avis"));
      await avancer(tester, 300);
      expect(find.text('Très bien a-2'), findsOneWidget);
      expect(find.text("Voir plus d'avis"), findsNothing);
      expect(
        serveur.requetes.any((r) => r.queryParameters['page'] == 2),
        isTrue,
      );
    });

    testWidgets('aucun avis : état vide', (tester) async {
      final serveur = AdaptateurSimule(
        _base({
          'GET /prestataire/avis': Simulee(200, _pageAvis([], note: null)),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.avisRecus);
      expect(find.text('Aucun avis pour le moment.'), findsOneWidget);
    });

    testWidgets('erreur serveur : bouton Réessayer', (tester) async {
      final serveur = AdaptateurSimule(
        _base({
          'GET /prestataire/avis': const Simulee(500, {
            'message': 'Erreur interne.',
          }),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.avisRecus);
      expect(find.text('Réessayer'), findsOneWidget);
    });
  });

  group('Signalements reçus', () {
    Map<String, Simulee> routes() => _base({
      'GET /prestataire/litiges': Simulee(
        200,
        _pageLitiges([
          _litige('l-1'),
          _litige('l-2', reponse: 'Je vous ai attendu.'),
          _litige('l-3', statut: 'cloture', reponse: 'Non.'),
        ]),
      ),
      'GET /prestataire/litiges/l-1': Simulee(200, _litige('l-1')),
    });

    testWidgets('onglets sur les vrais statuts, sans pastille de nombre', (
      tester,
    ) async {
      await _ouvrir(tester, AdaptateurSimule(routes()), Chemins.litigesRecus);
      expect(find.byKey(const ValueKey('litige-l-1')), findsOneWidget);
      expect(find.byKey(const ValueKey('litige-l-2')), findsNothing);
      expect(find.text('Répondre'), findsOneWidget);
      expect(find.textContaining('À traiter ('), findsNothing);

      await tester.tap(find.byKey(const ValueKey('onglet-enExamen')));
      await avancer(tester, 300);
      expect(find.byKey(const ValueKey('litige-l-2')), findsOneWidget);
      expect(find.text('Répondre'), findsNothing);

      await tester.tap(find.byKey(const ValueKey('onglet-clos')));
      await avancer(tester, 300);
      expect(find.byKey(const ValueKey('litige-l-3')), findsOneWidget);
    });

    testWidgets('une carte ouvre l’écran de réponse', (tester) async {
      await _ouvrir(tester, AdaptateurSimule(routes()), Chemins.litigesRecus);
      await tester.tap(find.byKey(const ValueKey('litige-l-1')));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.litigeRecu('l-1'));
    });

    testWidgets('onglet vide', (tester) async {
      final serveur = AdaptateurSimule(
        _base({'GET /prestataire/litiges': Simulee(200, _pageLitiges([]))}),
      );
      await _ouvrir(tester, serveur, Chemins.litigesRecus);
      expect(
        find.text('Aucun signalement à traiter pour le moment.'),
        findsOneWidget,
      );
    });
  });

  group('Réponse à un signalement', () {
    testWidgets('affiche le détail et envoie la réponse', (tester) async {
      final serveur = AdaptateurSimule(
        _detail(_litige('l-1'), {
          'POST /prestataire/litiges/l-1/reponse': const Simulee(200, {
            'message': 'ok',
          }),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.litigeRecu('l-1'));
      expect(find.text('Aminata D.'), findsOneWidget);
      expect(find.text('Prestataire absent'), findsOneWidget);
      expect(find.text('Ce que dit le client'), findsOneWidget);
      expect(find.text('Votre réponse'), findsOneWidget);

      await tester.enterText(
        find.byKey(const ValueKey('champ-reponse')),
        'Je vous ai attendu pendant une heure.',
      );
      await tester.tap(find.text('Envoyer ma réponse'));
      await avancer(tester, 300);
      final envoi = serveur.requetes.singleWhere((r) => r.method == 'POST');
      expect(envoi.path, '/prestataire/litiges/l-1/reponse');
      expect(envoi.data, {'reponse': 'Je vous ai attendu pendant une heure.'});
    });

    testWidgets('champ vide : rien n’est envoyé', (tester) async {
      final serveur = AdaptateurSimule(_detail(_litige('l-1')));
      await _ouvrir(tester, serveur, Chemins.litigeRecu('l-1'));
      await tester.tap(find.text('Envoyer ma réponse'));
      await avancer(tester, 300);
      expect(find.text('Votre réponse est obligatoire.'), findsOneWidget);
      expect(serveur.requetes.where((r) => r.method == 'POST'), isEmpty);
    });

    testWidgets('422 : message du serveur sous le champ', (tester) async {
      final serveur = AdaptateurSimule(
        _detail(_litige('l-1'), {
          'POST /prestataire/litiges/l-1/reponse': const Simulee(422, {
            'message': 'Les données sont invalides.',
            'errors': {
              'reponse': ['La réponse doit contenir au moins 10 caractères.'],
            },
          }),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.litigeRecu('l-1'));
      await tester.enterText(
        find.byKey(const ValueKey('champ-reponse')),
        'Non',
      );
      await tester.tap(find.text('Envoyer ma réponse'));
      await avancer(tester, 300);
      expect(
        find.text('La réponse doit contenir au moins 10 caractères.'),
        findsOneWidget,
      );
    });

    testWidgets('409 : message du serveur en bandeau', (tester) async {
      final serveur = AdaptateurSimule(
        _detail(_litige('l-1'), {
          'POST /prestataire/litiges/l-1/reponse': const Simulee(409, {
            'message': 'Vous avez déjà répondu à ce litige.',
          }),
        }),
      );
      await _ouvrir(tester, serveur, Chemins.litigeRecu('l-1'));
      await tester.enterText(
        find.byKey(const ValueKey('champ-reponse')),
        'Une réponse suffisamment longue.',
      );
      await tester.tap(find.text('Envoyer ma réponse'));
      await avancer(tester, 300);
      expect(find.text('Vous avez déjà répondu à ce litige.'), findsOneWidget);
    });

    testWidgets('lecture seule après réponse', (tester) async {
      final serveur = AdaptateurSimule(
        _detail(_litige('l-1', reponse: 'Je vous ai attendu.')),
      );
      await _ouvrir(tester, serveur, Chemins.litigeRecu('l-1'));
      expect(find.byKey(const ValueKey('reponse-envoyee')), findsOneWidget);
      expect(find.byKey(const ValueKey('champ-reponse')), findsNothing);
      expect(find.text('Envoyer ma réponse'), findsNothing);
    });
  });
}
