import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/push/controleur_push.dart';
import 'package:sopi/core/push/passerelle_messagerie.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/auth/session.dart';

import '../aide_api.dart';
import '../features/auth/aide_acces.dart';

/// Faux Firebase : aucun appel réel, tout est piloté par le test.
class FaussePasserelle implements PasserelleMessagerie {
  FaussePasserelle({
    this.configure = true,
    this.autorise = true,
    this.jetonFcm = 'jeton-fcm-1',
    this.initial,
  });

  final bool configure;
  final bool autorise;
  final String? jetonFcm;
  final MessagePush? initial;
  int permissionsDemandees = 0;
  int jetonsOublies = 0;

  final renouvele = StreamController<String>.broadcast();
  final premierPlan = StreamController<MessagePush>.broadcast();
  final toucher = StreamController<MessagePush>.broadcast();

  @override
  Future<bool> initialiser() async => configure;

  @override
  Future<bool> demanderPermission() async {
    permissionsDemandees++;
    return autorise;
  }

  @override
  Future<String?> jeton() async => jetonFcm;

  @override
  Stream<String> get jetonRenouvele => renouvele.stream;

  @override
  Stream<MessagePush> get recuAuPremierPlan => premierPlan.stream;

  @override
  Stream<MessagePush> get ouvertParToucher => toucher.stream;

  @override
  Future<MessagePush?> messageInitial() async => initial;

  @override
  Future<void> oublierJeton() async => jetonsOublies++;
}

const _client = {'id': '1', 'role': 'client', 'nom': 'Diallo', 'prenom': 'A'};

AdaptateurSimule _serveur({Map<String, dynamic> utilisateur = _client}) =>
    AdaptateurSimule({
      'GET /me': Simulee(200, utilisateur),
      'POST /appareils': const Simulee(201, {}),
      'DELETE /appareils/jeton-fcm-1': const Simulee(200, {}),
      'POST /auth/deconnexion': const Simulee(200, {}),
    });

List<String> _appels(AdaptateurSimule s) => [
  for (final r in s.requetes) '${r.method} ${r.path}',
];

Future<void> _laisser(WidgetTester tester) => avancer(tester, 300);

void main() {
  group('Notifications push', () {
    testWidgets('rien n\'est demandé tant que personne n\'est connecté', (
      tester,
    ) async {
      final faux = FaussePasserelle();
      final serveur = _serveur();
      final c = await lancerAcces(
        tester,
        serveur,
        surcharges: [passerelleMessagerieProvider.overrideWithValue(faux)],
      );
      c.read(pushProvider);
      await _laisser(tester);
      expect(faux.permissionsDemandees, 0);
      expect(_appels(serveur), isNot(contains('POST /appareils')));
    });

    testWidgets('après connexion : permission demandée, jeton envoyé', (
      tester,
    ) async {
      final faux = FaussePasserelle();
      final serveur = _serveur();
      final c = await lancerAcces(
        tester,
        serveur,
        jeton: 'sanctum',
        surcharges: [passerelleMessagerieProvider.overrideWithValue(faux)],
      );
      c.read(pushProvider);
      await _laisser(tester);

      expect(faux.permissionsDemandees, 1);
      final envoi = serveur.requetes.singleWhere(
        (r) => r.method == 'POST' && r.path == '/appareils',
      );
      expect(envoi.data, {'jeton': 'jeton-fcm-1', 'plateforme': 'android'});
    });

    testWidgets('jeton renouvelé : renvoyé au serveur, sans doublon', (
      tester,
    ) async {
      final faux = FaussePasserelle();
      final serveur = _serveur();
      final c = await lancerAcces(
        tester,
        serveur,
        jeton: 'sanctum',
        surcharges: [passerelleMessagerieProvider.overrideWithValue(faux)],
      );
      c.read(pushProvider);
      await _laisser(tester);
      faux.renouvele
        ..add('jeton-fcm-2')
        ..add('jeton-fcm-2');
      await _laisser(tester);

      final envois = serveur.requetes
          .where((r) => r.method == 'POST' && r.path == '/appareils')
          .map((r) => (r.data as Map)['jeton'])
          .toList();
      expect(envois, ['jeton-fcm-1', 'jeton-fcm-2']);
    });

    testWidgets(
      'déconnexion : l\'appareil est supprimé avant la fermeture du jeton',
      (tester) async {
        final faux = FaussePasserelle();
        final serveur = _serveur();
        final c = await lancerAcces(
          tester,
          serveur,
          jeton: 'sanctum',
          surcharges: [passerelleMessagerieProvider.overrideWithValue(faux)],
        );
        c.read(pushProvider);
        await _laisser(tester);
        await tester.runAsync(
          () => c.read(sessionProvider.notifier).deconnecter(),
        );
        await _laisser(tester);

        final appels = _appels(serveur);
        final suppression = appels.indexOf('DELETE /appareils/jeton-fcm-1');
        expect(suppression, isNonNegative);
        expect(suppression, lessThan(appels.indexOf('POST /auth/deconnexion')));
        expect(faux.jetonsOublies, 1);
        expect(cheminCourant(tester), Chemins.connexion);
      },
    );

    testWidgets('permission refusée : aucun jeton envoyé, l\'app continue', (
      tester,
    ) async {
      final faux = FaussePasserelle(autorise: false);
      final serveur = _serveur();
      final c = await lancerAcces(
        tester,
        serveur,
        jeton: 'sanctum',
        surcharges: [passerelleMessagerieProvider.overrideWithValue(faux)],
      );
      c.read(pushProvider);
      await _laisser(tester);

      expect(faux.permissionsDemandees, 1);
      expect(_appels(serveur), isNot(contains('POST /appareils')));
      expect(cheminCourant(tester), Chemins.accueil);
    });

    testWidgets('Firebase non configuré : aucune demande, l\'app continue', (
      tester,
    ) async {
      final faux = FaussePasserelle(configure: false);
      final serveur = _serveur();
      final c = await lancerAcces(
        tester,
        serveur,
        jeton: 'sanctum',
        surcharges: [passerelleMessagerieProvider.overrideWithValue(faux)],
      );
      c.read(pushProvider);
      await _laisser(tester);

      expect(faux.permissionsDemandees, 0);
      expect(_appels(serveur), isNot(contains('POST /appareils')));
      expect(cheminCourant(tester), Chemins.accueil);
    });

    testWidgets('envoi du jeton refusé par le serveur : l\'app continue', (
      tester,
    ) async {
      final faux = FaussePasserelle();
      final serveur = _serveur()
        ..routes['POST /appareils'] = const Simulee(500, {'message': 'Panne.'});
      final c = await lancerAcces(
        tester,
        serveur,
        jeton: 'sanctum',
        surcharges: [passerelleMessagerieProvider.overrideWithValue(faux)],
      );
      c.read(pushProvider);
      await _laisser(tester);
      expect(cheminCourant(tester), Chemins.accueil);
    });
  });

  group('Toucher une notification', () {
    Future<(FaussePasserelle, ProviderContainer)> ouvrir(
      WidgetTester tester, {
      MessagePush? initial,
      Map<String, dynamic> utilisateur = _client,
    }) async {
      final faux = FaussePasserelle(initial: initial);
      final c = await lancerAcces(
        tester,
        _serveur(utilisateur: utilisateur),
        jeton: 'sanctum',
        surcharges: [passerelleMessagerieProvider.overrideWithValue(faux)],
      );
      c.read(pushProvider);
      await _laisser(tester);
      return (faux, c);
    }

    testWidgets('réservation : ouvre le détail de la réservation', (
      tester,
    ) async {
      final (faux, _) = await ouvrir(tester);
      faux.toucher.add(
        const MessagePush(
          donnees: {'type': 'reservation_acceptee', 'reservationId': 'r-1'},
        ),
      );
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.detailReservation('r-1'));
    });

    testWidgets('prestation terminée : ouvre l\'écran d\'avis', (tester) async {
      final (faux, _) = await ouvrir(tester);
      faux.toucher.add(
        const MessagePush(
          donnees: {'type': 'reservation_terminee', 'reservationId': 'r-2'},
        ),
      );
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.avis('r-2'));
    });

    testWidgets('litige : ouvre le signalement', (tester) async {
      final (faux, c) = await ouvrir(tester);
      faux.toucher.add(
        const MessagePush(
          donnees: {
            'type': 'litige_cloture',
            'litigeId': 'l-1',
            'reservationId': 'r-1',
          },
        ),
      );
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.signalement('l-1'));
      await tester.pumpWidget(const SizedBox());
      c.dispose();
    });

    testWidgets('prestataire : nouvelle demande ouvre la demande reçue', (
      tester,
    ) async {
      final (faux, _) = await ouvrir(
        tester,
        utilisateur: {
          'id': '2',
          'role': 'prestataire',
          'nom': 'Ba',
          'prenom': 'Moussa',
        },
      );
      faux.toucher.add(
        const MessagePush(
          donnees: {
            'type': 'reservation_nouvelle_demande',
            'reservationId': 'r-3',
          },
        ),
      );
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.reservationPrestataire('r-3'));
    });

    testWidgets('type sans écran dédié : liste des notifications', (
      tester,
    ) async {
      final (faux, _) = await ouvrir(tester);
      faux.toucher.add(
        const MessagePush(
          donnees: {'type': 'sanction_posee', 'sanctionId': 's-1'},
        ),
      );
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.notifications);
    });

    testWidgets('application fermée puis ouverte par la notification', (
      tester,
    ) async {
      await ouvrir(
        tester,
        initial: const MessagePush(
          donnees: {'type': 'reservation_confirmee', 'reservationId': 'r-9'},
        ),
      );
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.detailReservation('r-9'));
    });
  });

  group('Premier plan', () {
    testWidgets('bandeau avec le texte du serveur, « Voir » ouvre l\'écran', (
      tester,
    ) async {
      final faux = FaussePasserelle();
      final c = await lancerAcces(
        tester,
        _serveur(),
        jeton: 'sanctum',
        messagerie: cleMessagerie,
        surcharges: [passerelleMessagerieProvider.overrideWithValue(faux)],
      );
      c.read(pushProvider);
      await _laisser(tester);

      faux.premierPlan.add(
        const MessagePush(
          titre: 'Demande acceptée',
          corps: 'Payez l\'acompte pour confirmer.',
          donnees: {'type': 'reservation_acceptee', 'reservationId': 'r-4'},
        ),
      );
      await avancer(tester, 400);
      expect(find.text('Demande acceptée'), findsOneWidget);
      expect(find.text('Payez l\'acompte pour confirmer.'), findsOneWidget);

      await tester.tap(find.text('Voir'));
      await passerTransition(tester);
      expect(cheminCourant(tester), Chemins.detailReservation('r-4'));
    });
  });
}
