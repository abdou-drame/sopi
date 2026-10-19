import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Notification reçue : le titre et le texte viennent du serveur, jamais de l'application.
class MessagePush {
  const MessagePush({
    this.titre = '',
    this.corps = '',
    this.donnees = const {},
  });

  final String titre;
  final String corps;

  /// Données du message, toutes en chaînes : `type`, `notificationId`, puis l'identifiant de l'objet
  /// (`reservationId`, `litigeId`, `avisId`, `sanctionId`).
  final Map<String, String> donnees;
}

/// Ce que l'application attend de Firebase. Abstraction pour la remplacer dans les tests
/// et pour que l'application fonctionne sans Firebase configuré.
abstract interface class PasserelleMessagerie {
  /// Initialise Firebase. Faux si Firebase n'est pas configuré (pas de google-services.json) : le push est alors désactivé.
  Future<bool> initialiser();

  /// Demande l'autorisation d'afficher des notifications. Faux si l'utilisateur refuse.
  Future<bool> demanderPermission();

  Future<String?> jeton();
  Stream<String> get jetonRenouvele;
  Stream<MessagePush> get recuAuPremierPlan;
  Stream<MessagePush> get ouvertParToucher;

  /// Notification qui a lancé l'application (touchée alors qu'elle était fermée).
  Future<MessagePush?> messageInitial();

  Future<void> oublierJeton();
}

MessagePush _depuis(RemoteMessage m) => MessagePush(
  titre: m.notification?.title ?? '',
  corps: m.notification?.body ?? '',
  donnees: {for (final e in m.data.entries) e.key: e.value.toString()},
);

class PasserelleFirebase implements PasserelleMessagerie {
  FirebaseMessaging get _fcm => FirebaseMessaging.instance;

  @override
  Future<bool> initialiser() async {
    try {
      if (Firebase.apps.isEmpty) await Firebase.initializeApp();
      return true;
    } catch (_) {
      return false;
    }
  }

  @override
  Future<bool> demanderPermission() async {
    final reglages = await _fcm.requestPermission();
    return reglages.authorizationStatus == AuthorizationStatus.authorized ||
        reglages.authorizationStatus == AuthorizationStatus.provisional;
  }

  @override
  // Les API getToken / onTokenRefresh / deleteToken sont signalées « dépréciées » dans firebase_messaging 17, mais elles
  // donnent le jeton d'enregistrement FCM, seul accepté par le backend (FCM HTTP v1, champ « token »). Les remplaçantes
  // (register / onRegistered) fournissent un identifiant d'installation Firebase, pas ce jeton.
  // ignore: deprecated_member_use
  Future<String?> jeton() => _fcm.getToken();

  @override
  // ignore: deprecated_member_use
  Stream<String> get jetonRenouvele => _fcm.onTokenRefresh;

  @override
  Stream<MessagePush> get recuAuPremierPlan =>
      FirebaseMessaging.onMessage.map(_depuis);

  @override
  Stream<MessagePush> get ouvertParToucher =>
      FirebaseMessaging.onMessageOpenedApp.map(_depuis);

  @override
  Future<MessagePush?> messageInitial() async {
    final m = await _fcm.getInitialMessage();
    return m == null ? null : _depuis(m);
  }

  @override
  // ignore: deprecated_member_use
  Future<void> oublierJeton() => _fcm.deleteToken();
}

final passerelleMessagerieProvider = Provider<PasserelleMessagerie>(
  (ref) => PasserelleFirebase(),
);
