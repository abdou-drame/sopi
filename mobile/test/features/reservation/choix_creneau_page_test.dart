import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:sopi/core/animation/chargement.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/core/widgets/decompte.dart';
import 'package:sopi/features/reservation/choix_creneau_page.dart';
import 'package:sopi/features/reservation/depot_reservation.dart';
import 'package:sopi/core/format/formateurs.dart';

import '../../aide_api.dart';
import '../auth/aide_acces.dart';
import 'aide_reservation.dart';

final _debutHorloge = DateTime.utc(2026, 10, 16, 8);
final _expiration = DateTime.utc(2026, 10, 16, 8, 5);

Map<String, Simulee> _routes({
  List<Map<String, Object?>>? agenda,
  Map<String, Simulee> enPlus = const {},
}) => routesClient({
  'GET /prestataires/p-1': Simulee(200, profilApi()),
  'GET /prestataires/p-1/agenda': Simulee(200, {
    'data':
        agenda ??
        [
          creneauApi('c-1', DateTime.utc(2026, 10, 16, 10)),
          creneauApi('c-2', DateTime.utc(2026, 10, 16, 11)),
          creneauApi('c-3', DateTime.utc(2026, 10, 18, 9)),
        ],
  }),
  'POST /creneaux/c-1/verrou': Simulee(200, {
    'creneauId': 'c-1',
    'verrouExpireA': _expiration.toIso8601String().replaceFirst('Z', '+00:00'),
  }),
  'POST /creneaux/c-2/verrou': Simulee(200, {
    'creneauId': 'c-2',
    'verrouExpireA': _expiration.toIso8601String().replaceFirst('Z', '+00:00'),
  }),
  'DELETE /creneaux/c-1/verrou': const Simulee(204, null),
  'DELETE /creneaux/c-2/verrou': const Simulee(204, null),
  ...enPlus,
});

/// Horloge de test déplaçable : le compte à rebours se règle dessus.
class _Horloge {
  DateTime maintenant = _debutHorloge;
  DateTime lire() => maintenant;
}

Future<_Horloge> _ouvrir(
  WidgetTester tester,
  AdaptateurSimule serveur, {
  String prestation = 'sv-1',
}) async {
  final horloge = _Horloge();
  await lancerAcces(
    tester,
    serveur,
    jeton: 'jeton',
    chemin: '/prestataires/p-1',
    surcharges: [horlogeProvider.overrideWithValue(horloge.lire)],
  );
  await passerTransition(tester);
  GoRouter.of(
    tester.element(find.byType(Scaffold).first),
  ).push('${Chemins.creneau('p-1')}?prestation=$prestation');
  await passerTransition(tester);
  return horloge;
}

int _compte(AdaptateurSimule s, String methode, String chemin) =>
    s.requetes.where((r) => r.method == methode && r.path == chemin).length;

void main() {
  setUpAll(initialiserFormats);

  group('Choix du créneau', () {
    testWidgets('chargement : squelettes gris puis heures', (tester) async {
      final serveur = AdaptateurLentPour(_routes(), {
        'GET /prestataires/p-1/agenda',
      });
      await _ouvrir(tester, serveur);
      expect(find.byType(Squelette), findsWidgets);
      expect(find.text('10h00'), findsNothing);
      serveur.liberer();
      await passerTransition(tester);
      expect(find.text('10h00'), findsOneWidget);
    });

    testWidgets('données : titre, mois, service, jours, heures, aide', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur);
      expect(find.text('Choisissez un'), findsOneWidget);
      expect(find.text('créneau'), findsOneWidget);
      expect(find.text('Salon Fatou'), findsWidgets);
      expect(find.text('Octobre 2026'), findsOneWidget);
      expect(find.text('Coupe femme · 1 h'), findsOneWidget);
      expect(find.text('Heures disponibles'), findsOneWidget);
      expect(find.text('10h00'), findsOneWidget);
      expect(find.text('11h00'), findsOneWidget);
      expect(find.textContaining('retenu 5 minutes'), findsOneWidget);
      expect(find.text('Choisissez une heure'), findsOneWidget);
      final agenda = serveur.requetes.firstWhere(
        (r) => r.path == '/prestataires/p-1/agenda',
      );
      expect(agenda.queryParameters['du'], '2026-10-16');
      expect(agenda.queryParameters['au'], '2026-10-29');
    });

    testWidgets('jour sans créneau : message vide', (tester) async {
      await _ouvrir(tester, AdaptateurSimule(_routes()));
      await toucher(tester, find.text('17'));
      await avancer(tester, 300);
      expect(find.text('Aucun créneau libre ce jour-là.'), findsOneWidget);
      expect(find.text('10h00'), findsNothing);
    });

    testWidgets('agenda entièrement vide : message', (tester) async {
      await _ouvrir(tester, AdaptateurSimule(_routes(agenda: [])));
      expect(find.text('Aucun créneau libre ce jour-là.'), findsOneWidget);
    });

    testWidgets('erreur de chargement : message et « Réessayer »', (
      tester,
    ) async {
      final routes = _routes(
        enPlus: {
          'GET /prestataires/p-1/agenda': const Simulee(503, {
            'message': 'Service momentanément indisponible.',
          }),
        },
      );
      await _ouvrir(tester, AdaptateurSimule(routes));
      expect(find.text('Service momentanément indisponible.'), findsOneWidget);
      expect(find.text('Réessayer'), findsOneWidget);
    });

    testWidgets('choisir une heure verrouille et lance le compte à rebours', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('10h00'));
      await avancer(tester, 200);
      expect(_compte(serveur, 'POST', '/creneaux/c-1/verrou'), 1);
      expect(find.text('Créneau retenu pour vous'), findsOneWidget);
      expect(find.text('05:00'), findsOneWidget);
      expect(find.text('Sam. 17 oct.'), findsNothing);
      expect(find.text('Ven. 16 oct.'), findsOneWidget);
      expect(find.text('Choisissez une heure'), findsNothing);
      expect(find.text('Continuer'), findsOneWidget);
      await _fermer(tester);
    });

    testWidgets('changer d\'heure libère d\'abord l\'ancien verrou', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('10h00'));
      await avancer(tester, 200);
      await toucher(tester, find.text('11h00'));
      await avancer(tester, 200);
      final ordre = serveur.requetes
          .where((r) => r.path.startsWith('/creneaux/'))
          .map((r) => '${r.method} ${r.path}')
          .toList();
      expect(ordre, [
        'POST /creneaux/c-1/verrou',
        'DELETE /creneaux/c-1/verrou',
        'POST /creneaux/c-2/verrou',
      ]);
      expect(find.text('Créneau retenu pour vous'), findsOneWidget);
      await _fermer(tester);
    });

    testWidgets('quitter l\'écran libère le verrou', (tester) async {
      final serveur = AdaptateurSimule(_routes());
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('10h00'));
      await avancer(tester, 200);
      await toucher(tester, find.bySemanticsLabel('Retour'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.fichePrestataire('p-1'));
      expect(_compte(serveur, 'DELETE', '/creneaux/c-1/verrou'), 1);
    });

    testWidgets('409 sur le verrou : message et grille rechargée', (
      tester,
    ) async {
      final routes = _routes(
        enPlus: {
          'POST /creneaux/c-1/verrou': const Simulee(409, {
            'message': "Ce créneau n'est plus disponible.",
          }),
        },
      );
      final serveur = AdaptateurSimule(routes);
      await _ouvrir(tester, serveur);
      expect(_compte(serveur, 'GET', '/prestataires/p-1/agenda'), 1);
      routes['GET /prestataires/p-1/agenda'] = Simulee(200, {
        'data': [creneauApi('c-2', DateTime.utc(2026, 10, 16, 11))],
      });
      await toucher(tester, find.text('10h00'));
      await avancer(tester, 300);
      expect(find.text("Ce créneau n'est plus disponible."), findsOneWidget);
      expect(_compte(serveur, 'GET', '/prestataires/p-1/agenda'), 2);
      expect(find.text('10h00'), findsNothing);
      expect(find.text('11h00'), findsOneWidget);
      expect(find.text('Créneau retenu pour vous'), findsNothing);
    });

    testWidgets('fin du compte à rebours : message et créneau désélectionné', (
      tester,
    ) async {
      final serveur = AdaptateurSimule(_routes());
      final horloge = await _ouvrir(tester, serveur);
      await toucher(tester, find.text('10h00'));
      await avancer(tester, 200);
      expect(find.byType(Decompte), findsOneWidget);
      horloge.maintenant = _expiration.add(const Duration(seconds: 1));
      await avancer(tester, 2000);
      expect(find.text(messageDelaiDepasse), findsOneWidget);
      expect(find.text('Créneau retenu pour vous'), findsNothing);
      expect(find.text('Choisissez une heure'), findsOneWidget);
    });

    testWidgets('Continuer crée la demande et ouvre « Demande envoyée »', (
      tester,
    ) async {
      final routes = _routes(
        enPlus: {
          'POST /reservations': Simulee(
            201,
            reservationApi('r-1', debut: DateTime.utc(2026, 10, 16, 10)),
          ),
        },
      );
      final serveur = AdaptateurSimule(routes);
      await _ouvrir(tester, serveur);
      await toucher(tester, find.text('10h00'));
      await avancer(tester, 200);
      await toucher(tester, find.text('Continuer'));
      await passerTransition(tester);
      final envoi = serveur.requetes.firstWhere(
        (r) => r.method == 'POST' && r.path == '/reservations',
      );
      expect(envoi.data, {'creneau_id': 'c-1', 'prestation_id': 'sv-1'});
      expect(cheminCourant(tester), Chemins.demandeEnvoyee('r-1'));
      // Le créneau est consommé par la demande : pas de libération.
      expect(_compte(serveur, 'DELETE', '/creneaux/c-1/verrou'), 0);
    });

    testWidgets('Continuer : 422 sur la prestation', (tester) async {
      final routes = _routes(
        enPlus: {
          'POST /reservations': const Simulee(422, {
            'message': 'Les données fournies sont invalides.',
            'errors': {
              'prestation_id': [
                "Cette prestation n'est pas proposée sur ce créneau.",
              ],
            },
          }),
        },
      );
      await _ouvrir(tester, AdaptateurSimule(routes));
      await toucher(tester, find.text('10h00'));
      await avancer(tester, 200);
      await toucher(tester, find.text('Continuer'));
      await avancer(tester, 300);
      expect(
        find.text("Cette prestation n'est pas proposée sur ce créneau."),
        findsOneWidget,
      );
      expect(cheminCourant(tester), Chemins.creneau('p-1'));
      await _fermer(tester);
    });

    testWidgets('Continuer : 409, le créneau est perdu', (tester) async {
      final routes = _routes(
        enPlus: {
          'POST /reservations': const Simulee(409, {
            'message': messageDelaiDepasse,
          }),
        },
      );
      await _ouvrir(tester, AdaptateurSimule(routes));
      await toucher(tester, find.text('10h00'));
      await avancer(tester, 200);
      await toucher(tester, find.text('Continuer'));
      await avancer(tester, 300);
      expect(find.text(messageDelaiDepasse), findsOneWidget);
      expect(find.text('Créneau retenu pour vous'), findsNothing);
    });

    testWidgets('Continuer : 429', (tester) async {
      final routes = _routes(
        enPlus: {
          'POST /reservations': const Simulee(
            429,
            {'message': 'Trop de tentatives.'},
            {'retry-after': '30'},
          ),
        },
      );
      await _ouvrir(tester, AdaptateurSimule(routes));
      await toucher(tester, find.text('10h00'));
      await avancer(tester, 200);
      await toucher(tester, find.text('Continuer'));
      await avancer(tester, 300);
      expect(find.text('Trop de tentatives.'), findsOneWidget);
      await _fermer(tester);
    });

    testWidgets('sans service dans l\'adresse : invitation à en choisir un', (
      tester,
    ) async {
      await _ouvrir(tester, AdaptateurSimule(_routes()), prestation: '');
      expect(
        find.text("Choisissez d'abord un service sur la fiche."),
        findsOneWidget,
      );
    });

    testWidgets('petit écran 320×568 : pas de débordement', (tester) async {
      tester.view.physicalSize = const Size(320, 568);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await _ouvrir(tester, AdaptateurSimule(_routes()));
      await toucher(tester, find.text('10h00'));
      await avancer(tester, 200);
      expect(tester.takeException(), isNull);
      await _fermer(tester);
    });
  });
}

/// Démonte l'écran (ce qui libère le verrou) et laisse la requête de libération se terminer.
Future<void> _fermer(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await avancer(tester, 100);
}
