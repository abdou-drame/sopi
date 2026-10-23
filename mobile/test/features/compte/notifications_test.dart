import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/format/formateurs.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/compte/modeles_notification.dart';
import 'package:sopi/features/compte/notifications_page.dart';
import 'package:sopi/features/reservation/depot_reservation.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import '../reservation/aide_reservation.dart';

final _maintenant = DateTime.utc(2026, 10, 25, 12, 0);

Map<String, Object?> _notif(
  String id,
  String type, {
  String titre = 'Titre',
  String corps = 'Corps',
  bool lue = false,
  DateTime? date,
  Map<String, Object?>? donnees,
}) => {
  'id': id,
  'type': type,
  'titre': titre,
  'corps': corps,
  'donnees': donnees ?? {'reservationId': 'r-1'},
  'lue': lue,
  'lueLe': lue ? '2026-10-25T11:00:00+00:00' : null,
  'dateCreation': (date ?? _maintenant.subtract(const Duration(minutes: 5)))
      .toIso8601String(),
};

Map<String, Object?> _page(List<Object?> data, int page, int derniere) => {
  'data': data,
  'meta': {'current_page': page, 'last_page': derniere, 'per_page': 20},
};

Future<(AdaptateurSimule, ProviderContainer)> _ouvrir(
  WidgetTester tester,
  Map<String, Simulee> routes,
) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  final serveur = AdaptateurSimule(
    routesClient({
      'GET /notifications/non-lues/compteur': const Simulee(200, {
        'nonLues': 2,
      }),
      'POST /notifications/lire-tout': const Simulee(200, {'marquees': 2}),
      ...routes,
    }),
  );
  final conteneur = await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: Chemins.notifications,
    surcharges: [horlogeProvider.overrideWithValue(() => _maintenant)],
  );
  await passerTransition(tester);
  return (serveur, conteneur);
}

int _appels(AdaptateurSimule s, String cle) =>
    s.requetes.where((r) => '${r.method} ${r.path}' == cle).length;

void main() {
  setUpAll(initialiserFormats);

  test('dates relatives', () {
    String f(Duration d) => dateRelative(_maintenant.subtract(d), _maintenant);
    expect(f(const Duration(seconds: 20)), "À l'instant");
    expect(f(const Duration(minutes: 5)), 'Il y a 5 min');
    expect(f(const Duration(hours: 2)), 'Il y a 2 h');
    expect(f(const Duration(hours: 14)), 'Hier');
    expect(f(const Duration(days: 6)), 'Lun. 19 oct.');
  });

  test('destination selon le type', () {
    NotificationClient n(String type, Map<String, String> d) =>
        NotificationClient(
          id: 'n',
          type: type,
          titre: '',
          corps: '',
          dateCreation: _maintenant,
          donnees: d,
        );
    expect(
      destinationNotification(
        n('reservation_acceptee', {'reservationId': 'r'}),
      ),
      '/reservations/r',
    );
    expect(
      destinationNotification(
        n('reservation_terminee', {'reservationId': 'r'}),
      ),
      '/reservations/r/avis',
    );
    expect(
      destinationNotification(n('litige_cloture', {'litigeId': 'l'})),
      '/signalements/l',
    );
    expect(destinationNotification(n('sanction_posee', {})), isNull);
    expect(destinationNotification(n('type_inconnu', {})), isNull);
  });

  test(
    'rappel de rendez-vous : icône dédiée et ouverture de la réservation',
    () {
      expect(iconeNotification('rappel_rendez_vous'), Icons.alarm_rounded);
      expect(
        iconeNotification('rappel_rendez_vous'),
        isNot(iconeNotification('type_inconnu')),
      );
      final rappel = NotificationClient(
        id: 'n',
        type: 'rappel_rendez_vous',
        titre: '',
        corps: '',
        dateCreation: _maintenant,
        donnees: const {'reservationId': 'r'},
      );
      expect(destinationNotification(rappel), '/reservations/r');
    },
  );

  testWidgets('liste : titre, texte, date relative et pastille non lue', (
    tester,
  ) async {
    await _ouvrir(tester, {
      'GET /notifications': Simulee(
        200,
        _page(
          [
            _notif(
              'n-1',
              'reservation_confirmee',
              titre: 'Réservation confirmée',
              corps: 'Votre rendez-vous est confirmé.',
            ),
            _notif(
              'n-2',
              'reservation_expiree',
              titre: 'Réservation expirée',
              lue: true,
              date: _maintenant.subtract(const Duration(days: 1, hours: 2)),
            ),
          ],
          1,
          1,
        ),
      ),
    });
    expect(find.text('Réservation confirmée'), findsOneWidget);
    expect(find.text('Votre rendez-vous est confirmé.'), findsOneWidget);
    expect(find.text('Il y a 5 min'), findsOneWidget);
    expect(find.text('Hier'), findsOneWidget);
    expect(find.byKey(const ValueKey('pastille-non-lue')), findsOneWidget);
    final nonLue = tester.widget<Text>(find.text('Réservation confirmée'));
    final lue = tester.widget<Text>(find.text('Réservation expirée'));
    // Maquette : tous les titres sont en gras (700) ; la pastille bleue signale le non lu.
    expect(nonLue.style!.fontWeight, FontWeight.w700);
    expect(lue.style!.fontWeight, FontWeight.w700);
  });

  testWidgets('liste vide, puis erreur avec Réessayer', (tester) async {
    await _ouvrir(tester, {
      'GET /notifications': Simulee(200, _page([], 1, 1)),
    });
    expect(find.text(messageAucuneNotification), findsOneWidget);
  });

  testWidgets('erreur serveur : message et Réessayer', (tester) async {
    await _ouvrir(tester, {
      'GET /notifications': const Simulee(503, {'message': 'Indisponible.'}),
    });
    expect(find.text('Indisponible.'), findsOneWidget);
    expect(find.text('Réessayer'), findsOneWidget);
  });

  testWidgets('pagination : la page 2 est demandée en fin de liste', (
    tester,
  ) async {
    final (serveur, _) = await _ouvrir(tester, {
      'GET /notifications': Simulee(
        200,
        _page(
          [for (var i = 0; i < 20; i++) _notif('n-$i', 'reservation_acceptee')],
          1,
          2,
        ),
      ),
    });
    await tester.drag(find.byType(CustomScrollView), const Offset(0, -8000));
    await tester.pump();
    await passerTransition(tester);
    final pages = serveur.requetes
        .where((r) => r.path == '/notifications')
        .map((r) => r.queryParameters['page']);
    expect(pages, containsAll([1, 2]));
  });

  testWidgets(
    'toucher : marque lue, rafraîchit le compteur, ouvre la réservation',
    (tester) async {
      final (serveur, conteneur) = await _ouvrir(tester, {
        'GET /notifications': Simulee(
          200,
          _page(
            [_notif('n-1', 'reservation_acceptee', titre: 'Demande acceptée')],
            1,
            1,
          ),
        ),
        'POST /notifications/n-1/lire': Simulee(
          200,
          _notif('n-1', 'reservation_acceptee', lue: true),
        ),
        'GET /reservations/r-1': Simulee(200, {
          'data': reservationApi('r-1', debut: DateTime.utc(2026, 10, 27, 10)),
        }),
      });
      conteneur.listen(notificationsNonLuesProvider, (_, _) {});
      await passerTransition(tester);
      final avant = _appels(serveur, 'GET /notifications/non-lues/compteur');
      await tester.tap(find.text('Demande acceptée'));
      await tester.pump();
      await passerTransition(tester);
      expect(_appels(serveur, 'POST /notifications/n-1/lire'), 1);

      expect(
        _appels(serveur, 'GET /notifications/non-lues/compteur'),
        greaterThan(avant),
      );
      expect(cheminCourant(tester), '/reservations/r-1');
    },
  );

  testWidgets('prestation terminée : ouvre l écran d avis', (tester) async {
    await _ouvrir(tester, {
      'GET /notifications': Simulee(
        200,
        _page(
          [_notif('n-1', 'reservation_terminee', titre: 'Prestation terminée')],
          1,
          1,
        ),
      ),
      'POST /notifications/n-1/lire': Simulee(
        200,
        _notif('n-1', 'reservation_terminee', lue: true),
      ),
      'GET /reservations/r-1': Simulee(200, {
        'data': reservationApi(
          'r-1',
          debut: DateTime.utc(2026, 10, 24, 10),
          statut: 'terminee',
          peutAnnuler: false,
          peutNoter: true,
        ),
      }),
    });
    await tester.tap(find.text('Prestation terminée'));
    await tester.pump();
    await passerTransition(tester);
    expect(cheminCourant(tester), '/reservations/r-1/avis');
  });

  testWidgets('type inconnu : marquée lue, aucune navigation', (tester) async {
    final (serveur, _) = await _ouvrir(tester, {
      'GET /notifications': Simulee(
        200,
        _page(
          [_notif('n-1', 'type_inconnu', titre: 'Nouveauté', donnees: {})],
          1,
          1,
        ),
      ),
      'POST /notifications/n-1/lire': Simulee(
        200,
        _notif('n-1', 'type_inconnu', lue: true, donnees: {}),
      ),
    });
    await tester.tap(find.text('Nouveauté'));
    await tester.pump();
    await passerTransition(tester);
    expect(_appels(serveur, 'POST /notifications/n-1/lire'), 1);
    expect(cheminCourant(tester), Chemins.notifications);
    expect(find.byKey(const ValueKey('pastille-non-lue')), findsNothing);
  });

  testWidgets('tout marquer comme lu : appel et pastilles retirées', (
    tester,
  ) async {
    final (serveur, _) = await _ouvrir(tester, {
      'GET /notifications': Simulee(
        200,
        _page(
          [
            _notif('n-1', 'reservation_acceptee'),
            _notif('n-2', 'reservation_confirmee'),
          ],
          1,
          1,
        ),
      ),
    });
    expect(find.byKey(const ValueKey('pastille-non-lue')), findsNWidgets(2));
    await tester.tap(find.text('Tout marquer comme lu'));
    await tester.pump();
    await passerTransition(tester);
    expect(_appels(serveur, 'POST /notifications/lire-tout'), 1);
    expect(find.byKey(const ValueKey('pastille-non-lue')), findsNothing);
  });
}
