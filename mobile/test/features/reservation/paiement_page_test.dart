import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_reservation.dart';

final _debut = DateTime.utc(2026, 10, 24, 10);
const _lien = 'https://pay.exemple.sn/session/abc123';

Map<String, Object?> _res({
  String statut = 'acceptee',
  bool peutPayer = true,
  Map<String, Object?>? paiement,
  String? code,
}) => reservationApi(
  'r-1',
  debut: _debut,
  statut: statut,
  peutAnnuler: true,
  peutPayer: peutPayer,
  paiement: paiement,
  code: code,
);

Simulee _session({String url = _lien}) => Simulee(201, {
  'paiement': {
    'id': 'pay-1',
    'statut': 'en_attente',
    'montantAcompte': 1500,
    'devise': 'XOF',
  },
  'montantRestantSurPlace': 3500,
  'urlPaiement': url,
  'reservation': {'id': 'r-1', 'statut': 'acceptee'},
});

AdaptateurSimule _serveur({
  Map<String, Object?>? reservation,
  Map<String, Simulee> routes = const {},
}) => AdaptateurSimule(
  routesClient({
    'GET /reservations/r-1': Simulee(200, {'data': reservation ?? _res()}),
    'GET /prestataires/p-1': Simulee(200, profilApi()),
    'POST /reservations/r-1/payer': _session(),
    ...routes,
  }),
);

void _serveReservation(AdaptateurSimule serveur, Map<String, Object?> r) {
  serveur.routes['GET /reservations/r-1'] = Simulee(200, {'data': r});
}

int _lectures(AdaptateurSimule serveur) => serveur.requetes
    .where((r) => r.method == 'GET' && r.path == '/reservations/r-1')
    .length;

Future<void> _ouvrir(
  WidgetTester tester,
  AdaptateurSimule serveur, {
  List<Uri>? urls,
}) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: Chemins.paiement('r-1'),
    urlsOuvertes: urls,
  );
  await passerTransition(tester);
}

/// Le client quitte l'application (page de paiement) puis y revient.
Future<void> _revenir(WidgetTester tester) async {
  final b = tester.binding;
  for (final e in [
    AppLifecycleState.inactive,
    AppLifecycleState.hidden,
    AppLifecycleState.paused,
    AppLifecycleState.hidden,
    AppLifecycleState.inactive,
    AppLifecycleState.resumed,
  ]) {
    b.handleAppLifecycleStateChanged(e);
  }
  await tester.pump();
}

Future<void> _payer(WidgetTester tester) async {
  await toucher(tester, find.text('Payer ${formaterArgent(1500)}'));
  await avancer(tester, 200);
}

void main() {
  setUpAll(initialiserFormats);

  group('Paiement : récapitulatif', () {
    testWidgets('montants et textes de la maquette, venant de l\'API', (
      tester,
    ) async {
      await _ouvrir(tester, _serveur());
      expect(find.text('Confirmez et'), findsOneWidget);
      expect(find.text('payez l\'acompte'), findsOneWidget);
      expect(find.text('Salon Fatou'), findsOneWidget);
      expect(find.text('Coupe femme'), findsOneWidget);
      expect(find.text(formaterDate(_debut)), findsOneWidget);
      expect(find.text(formaterArgent(5000)), findsOneWidget);
      expect(find.text(formaterArgent(3500)), findsOneWidget);
      expect(find.text('Acompte à payer maintenant (30 %)'), findsOneWidget);
      expect(find.text(formaterArgent(1500)), findsOneWidget);
      expect(
        find.text(
          'Paiement sécurisé. Vous choisirez Wave ou Orange Money sur la page suivante.',
        ),
        findsOneWidget,
      );
      expect(
        find.textContaining(
          'l\'acompte n\'est pas remboursé',
          skipOffstage: false,
        ),
        findsOneWidget,
      );
      expect(find.textContaining('Il reste'), findsNothing);
      expect(find.text('Payer ${formaterArgent(1500)}'), findsOneWidget);
    });

    testWidgets('réservation non payable : pas de bouton de paiement', (
      tester,
    ) async {
      await _ouvrir(tester, _serveur(reservation: _res(peutPayer: false)));
      expect(find.textContaining('Payer 1'), findsNothing);
      expect(
        find.text('Cette réservation ne peut pas être payée pour le moment.'),
        findsOneWidget,
      );
    });

    testWidgets('petit écran 320×568 : pas de débordement', (tester) async {
      tester.view.physicalSize = const Size(320, 568);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await lancerAcces(
        tester,
        _serveur(),
        jeton: 'jeton',
        chemin: Chemins.paiement('r-1'),
      );
      await passerTransition(tester);
      expect(tester.takeException(), isNull);
    });
  });

  group('Paiement : création de la session', () {
    testWidgets('« Payer » appelle l\'API sans corps et ouvre le lien reçu', (
      tester,
    ) async {
      final urls = <Uri>[];
      final serveur = _serveur();
      await _ouvrir(tester, serveur, urls: urls);
      await _payer(tester);
      final appels = serveur.requetes.where(
        (r) => r.method == 'POST' && r.path == '/reservations/r-1/payer',
      );
      expect(appels, hasLength(1));
      expect(appels.single.data, isNull);
      expect(urls, [Uri.parse(_lien)]);
    });

    for (final cas in [
      (409, 'Le délai de paiement est dépassé.'),
      (409, 'Cette réservation est déjà payée.'),
      (
        409,
        'Un paiement est déjà en cours d\'initialisation. Veuillez réessayer dans un instant.',
      ),
      (
        503,
        'Le service de paiement est momentanément indisponible. Veuillez réessayer.',
      ),
      (429, 'Trop de tentatives. Réessayez dans 30 secondes.'),
    ]) {
      testWidgets('${cas.$1} : « ${cas.$2} » sous le bouton', (tester) async {
        final urls = <Uri>[];
        final serveur = _serveur(
          routes: {
            'POST /reservations/r-1/payer': Simulee(cas.$1, {
              'message': cas.$2,
            }),
          },
        );
        await _ouvrir(tester, serveur, urls: urls);
        await _payer(tester);
        expect(find.text(cas.$2), findsOneWidget);
        expect(urls, isEmpty);
        expect(find.text('Payer ${formaterArgent(1500)}'), findsOneWidget);
      });
    }

    testWidgets('un lien qui n\'est pas en https n\'est jamais ouvert', (
      tester,
    ) async {
      final urls = <Uri>[];
      final serveur = _serveur(
        routes: {
          'POST /reservations/r-1/payer': _session(
            url: 'http://pirate.exemple/x',
          ),
        },
      );
      await _ouvrir(tester, serveur, urls: urls);
      await _payer(tester);
      expect(urls, isEmpty);
      expect(find.textContaining('adresse invalide'), findsOneWidget);
    });

    testWidgets(
      'retour dans l\'application sans avoir ouvert de lien : rien à vérifier',
      (tester) async {
        final serveur = _serveur();
        await _ouvrir(tester, serveur);
        await _revenir(tester);
        await avancer(tester, 500);
        expect(find.text('Vérification du paiement…'), findsNothing);
      },
    );
  });

  group('Paiement : retour de la page de paiement', () {
    testWidgets('attente puis confirmation : écran « Paiement réussi »', (
      tester,
    ) async {
      final serveur = _serveur();
      await _ouvrir(tester, serveur);
      await _payer(tester);
      await _revenir(tester);
      await avancer(tester, 200);
      expect(find.text('Vérification du paiement…'), findsOneWidget);
      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      _serveReservation(
        serveur,
        _res(
          statut: 'confirmee',
          peutPayer: false,
          code: 'K7MQ4Z',
          paiement: {'statut': 'reussi', 'montantAcompte': 1500},
        ),
      );
      await avancer(tester, 3200);
      expect(find.text('réussi !'), findsOneWidget);
      expect(
        find.text(
          'Votre réservation est confirmée. Le prestataire a été prévenu.',
        ),
        findsOneWidget,
      );
      expect(find.text('Salon Fatou'), findsOneWidget);
      expect(find.text('Acompte payé'), findsOneWidget);
      expect(find.text(formaterArgent(1500)), findsOneWidget);
      expect(find.text('Voir ma réservation'), findsOneWidget);

      await toucher(tester, find.text('Voir ma réservation'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.detailReservation('r-1'));
    });

    testWidgets('« Retour à l\'accueil » mène à l\'accueil', (tester) async {
      final serveur = _serveur();
      await _ouvrir(tester, serveur);
      await _payer(tester);
      _serveReservation(
        serveur,
        _res(
          statut: 'confirmee',
          peutPayer: false,
          paiement: {'statut': 'reussi', 'montantAcompte': 1500},
        ),
      );
      await _revenir(tester);
      await avancer(tester, 400);
      await toucher(tester, find.text('Retour à l\'accueil'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.accueil);
    });

    testWidgets('paiement échoué : écran « non abouti » et nouvel essai', (
      tester,
    ) async {
      final serveur = _serveur();
      await _ouvrir(tester, serveur);
      await _payer(tester);
      _serveReservation(
        serveur,
        _res(paiement: {'statut': 'echoue', 'montantAcompte': 1500}),
      );
      await _revenir(tester);
      await avancer(tester, 400);
      expect(find.text('non abouti'), findsOneWidget);
      expect(
        find.text(
          'Aucun montant n\'a été débité. Vous pouvez réessayer ou choisir un autre moyen de paiement.',
        ),
        findsOneWidget,
      );
      expect(
        find.textContaining('conservé pendant un délai limité'),
        findsOneWidget,
      );
      expect(find.text('Mes réservations'), findsOneWidget);

      _serveReservation(serveur, _res());
      await toucher(tester, find.text('Réessayer le paiement'));
      await avancer(tester, 500);
      expect(find.text('Confirmez et'), findsOneWidget);
      expect(find.text('Payer ${formaterArgent(1500)}'), findsOneWidget);
    });

    testWidgets('« Mes réservations » depuis l\'échec', (tester) async {
      final serveur = _serveur();
      await _ouvrir(tester, serveur);
      await _payer(tester);
      _serveReservation(
        serveur,
        _res(paiement: {'statut': 'echoue', 'montantAcompte': 1500}),
      );
      await _revenir(tester);
      await avancer(tester, 400);
      await toucher(tester, find.text('Mes réservations'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.reservations);
    });

    testWidgets('sans réponse pendant 3 minutes : paiement non abouti', (
      tester,
    ) async {
      final serveur = _serveur();
      await _ouvrir(tester, serveur);
      await _payer(tester);
      await _revenir(tester);
      await avancer(tester, 170000);
      expect(find.text('Vérification du paiement…'), findsOneWidget);
      // Une lecture toutes les 3 s, la première immédiate.
      expect(_lectures(serveur), inInclusiveRange(55, 61));
      await avancer(tester, 12000);
      expect(find.text('non abouti'), findsOneWidget);
    });

    testWidgets('« Vérifier de nouveau » relance la vérification', (
      tester,
    ) async {
      final serveur = _serveur();
      await _ouvrir(tester, serveur);
      await _payer(tester);
      await _revenir(tester);
      await avancer(tester, 200);
      final avant = _lectures(serveur);
      _serveReservation(
        serveur,
        _res(
          statut: 'confirmee',
          peutPayer: false,
          paiement: {'statut': 'reussi', 'montantAcompte': 1500},
        ),
      );
      await toucher(tester, find.text('Vérifier de nouveau'));
      await avancer(tester, 300);
      expect(_lectures(serveur), greaterThan(avant));
      expect(find.text('réussi !'), findsOneWidget);
    });

    testWidgets('une coupure réseau passagère ne conclut pas', (tester) async {
      final serveur = _serveur();
      await _ouvrir(tester, serveur);
      await _payer(tester);
      serveur.reseauCoupe = true;
      await _revenir(tester);
      await avancer(tester, 7000);
      expect(find.text('Vérification du paiement…'), findsOneWidget);
      serveur.reseauCoupe = false;
      _serveReservation(
        serveur,
        _res(
          statut: 'confirmee',
          peutPayer: false,
          paiement: {'statut': 'reussi', 'montantAcompte': 1500},
        ),
      );
      await avancer(tester, 3200);
      expect(find.text('réussi !'), findsOneWidget);
    });
  });
}
