import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/session.dart';
import '../../features/auth/utilisateur.dart';
import '../../features/compte/modeles_notification.dart';
import '../api_client.dart';
import '../routing/chemins.dart';
import '../routing/routeur.dart';
import 'passerelle_messagerie.dart';

/// Messagerie de l'application : le bandeau des notifications reçues au premier plan s'affiche par elle.
final cleMessagerie = GlobalKey<ScaffoldMessengerState>();

/// Notifications push. Actif tant qu'un utilisateur est connecté : demande l'autorisation, enregistre le jeton FCM
/// auprès du serveur, le renvoie s'il change, affiche les notifications reçues au premier plan et ouvre l'écran
/// concerné au toucher. Toute erreur est absorbée : le push ne bloque jamais l'application.
class ControleurPush {
  ControleurPush(this._ref);

  final Ref _ref;
  final _abonnements = <StreamSubscription<Object?>>[];
  bool _actif = false;
  bool _initialise = false;
  String? _jetonEnvoye;
  String? _jetonEnCours;

  PasserelleMessagerie get _passerelle =>
      _ref.read(passerelleMessagerieProvider);

  /// À la connexion (jamais au tout premier lancement : l'autorisation est demandée une fois connecté).
  Future<void> activer() async {
    if (_actif) return;
    _actif = true;
    try {
      _initialise = await _passerelle.initialiser();
      if (!_initialise || !_actif) return;
      if (!await _passerelle.demanderPermission() || !_actif) {
        return; // Refus : l'application continue sans notifications.
      }
      _abonnements
        ..add(_passerelle.jetonRenouvele.listen(_envoyer))
        ..add(_passerelle.recuAuPremierPlan.listen(_afficherBandeau))
        ..add(_passerelle.ouvertParToucher.listen(_ouvrir));
      final jeton = await _passerelle.jeton();
      if (jeton != null && _actif) await _envoyer(jeton);
      final initial = await _passerelle.messageInitial();
      if (initial != null && _actif) _ouvrir(initial);
    } catch (_) {
      // Firebase indisponible ou erreur réseau : pas de push, rien d'autre ne change.
    }
  }

  /// Arrête l'écoute (déconnexion, session expirée).
  void desactiver() {
    _actif = false;
    for (final a in _abonnements) {
      a.cancel();
    }
    _abonnements.clear();
  }

  /// À appeler AVANT la fermeture de la session côté serveur : supprime l'appareil du compte, puis le jeton local.
  Future<void> retirerAppareil() async {
    desactiver();
    if (!_initialise) return;
    try {
      final jeton = _jetonEnvoye ?? await _passerelle.jeton();
      if (jeton != null) {
        await _ref
            .read(dioProvider)
            .delete<Object?>('/appareils/${Uri.encodeComponent(jeton)}');
      }
    } catch (_) {
      // Hors ligne : le serveur réattribuera ce jeton au prochain compte qui se connecte.
    }
    _jetonEnvoye = null;
    try {
      await _passerelle.oublierJeton();
    } catch (_) {}
  }

  Future<void> _envoyer(String jeton) async {
    if (jeton == _jetonEnvoye || jeton == _jetonEnCours) return;
    _jetonEnCours = jeton;
    try {
      await _ref
          .read(dioProvider)
          .post<Object?>(
            '/appareils',
            data: {
              'jeton': jeton,
              'plateforme': defaultTargetPlatform == TargetPlatform.iOS
                  ? 'ios'
                  : 'android',
            },
          );
      _jetonEnvoye = jeton;
    } catch (_) {
      // Sera retenté à la prochaine connexion ou au prochain renouvellement du jeton.
    } finally {
      _jetonEnCours = null;
    }
  }

  /// Écran à ouvrir pour ce message : celui de la notification, sinon la liste des notifications.
  String destination(MessagePush m) {
    final session = _ref.read(sessionProvider);
    final notification = NotificationClient(
      id: m.donnees['notificationId'] ?? '',
      type: m.donnees['type'] ?? '',
      titre: m.titre,
      corps: m.corps,
      dateCreation: DateTime.now().toUtc(),
      donnees: m.donnees,
    );
    return destinationNotification(
          notification,
          prestataire:
              session is Connecte &&
              session.utilisateur.role == RoleSopi.prestataire,
        ) ??
        Chemins.notifications;
  }

  void _ouvrir(MessagePush m) {
    if (_ref.read(sessionProvider) is! Connecte) return;
    try {
      _ref.read(routeurProvider).push(destination(m));
    } catch (_) {}
  }

  void _afficherBandeau(MessagePush m) {
    final messagerie = cleMessagerie.currentState;
    if (messagerie == null || (m.titre.isEmpty && m.corps.isEmpty)) return;
    messagerie
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 6),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (m.titre.isNotEmpty)
                Text(
                  m.titre,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              if (m.corps.isNotEmpty) Text(m.corps),
            ],
          ),
          action: SnackBarAction(label: 'Voir', onPressed: () => _ouvrir(m)),
        ),
      );
  }
}

final pushProvider = Provider<ControleurPush>((ref) {
  final controleur = ControleurPush(ref);
  ref.listen<EtatSession>(sessionProvider, (_, session) {
    if (session is Connecte) {
      unawaited(controleur.activer());
    } else {
      controleur.desactiver();
    }
  }, fireImmediately: true);
  final session = ref.read(sessionProvider.notifier);
  session.avantDeconnexion = controleur.retirerAppareil;
  ref.onDispose(() {
    session.avantDeconnexion = null;
    controleur.desactiver();
  });
  return controleur;
});
