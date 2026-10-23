import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/routing/chemins.dart';
import '../../core/widgets/liste_infinie.dart';

/// Notification dans l'application (GET /notifications).
class NotificationClient {
  const NotificationClient({
    required this.id,
    required this.type,
    required this.titre,
    required this.corps,
    required this.dateCreation,
    this.donnees = const {},
    this.lue = false,
  });

  final String id;
  final String type;
  final String titre;
  final String corps;
  final DateTime dateCreation;
  final Map<String, String> donnees;
  final bool lue;

  NotificationClient copieLue() => NotificationClient(
    id: id,
    type: type,
    titre: titre,
    corps: corps,
    dateCreation: dateCreation,
    donnees: donnees,
    lue: true,
  );

  factory NotificationClient.depuisJson(Map<String, dynamic> j) {
    final brutes = j['donnees'] is Map ? j['donnees'] as Map : const {};
    return NotificationClient(
      id: j['id'].toString(),
      type: (j['type'] ?? '').toString(),
      titre: (j['titre'] ?? '').toString(),
      corps: (j['corps'] ?? '').toString(),
      dateCreation: DateTime.parse(j['dateCreation'].toString()).toUtc(),
      donnees: {
        for (final e in brutes.entries) e.key.toString(): e.value.toString(),
      },
      lue: j['lue'] == true,
    );
  }

  static PageListe<NotificationClient> pageDepuisJson(Object? corps) {
    final brut = corps is Map ? Map<String, dynamic>.from(corps) : const {};
    final meta = brut['meta'] is Map ? brut['meta'] as Map : const {};
    return PageListe(
      elements: [
        for (final e in (brut['data'] as List? ?? const []))
          if (e is Map)
            NotificationClient.depuisJson(Map<String, dynamic>.from(e)),
      ],
      page: (meta['current_page'] as num?)?.toInt() ?? 1,
      derniere: (meta['last_page'] as num?)?.toInt() ?? 1,
    );
  }
}

/// « Il y a 5 min », « Il y a 2 h », « Hier », puis « Lun. 19 oct. ». Les jours se comptent en UTC
/// (Africa/Dakar est à UTC+0). Nécessite `initialiserFormats`.
String dateRelative(DateTime date, DateTime maintenant) {
  final d = date.toUtc();
  final m = maintenant.toUtc();
  final ecart = m.difference(d);
  if (ecart.inMinutes < 1) return "À l'instant";
  if (ecart.inMinutes < 60) return 'Il y a ${ecart.inMinutes} min';
  final jourDate = DateTime.utc(d.year, d.month, d.day);
  final jourMaintenant = DateTime.utc(m.year, m.month, m.day);
  final jours = jourMaintenant.difference(jourDate).inDays;
  if (jours <= 0) return 'Il y a ${ecart.inHours} h';
  if (jours == 1) return 'Hier';
  final texte = DateFormat('EEE d MMM', 'fr_FR').format(d);
  return '${texte[0].toUpperCase()}${texte.substring(1)}';
}

/// Écran à ouvrir au toucher d'une notification, ou null (la notification est seulement marquée lue).
///
/// - réservation (`reservationId`) : détail de la réservation ; « prestation terminée » : écran d'avis ;
/// - litige (`litigeId`) : détail du signalement ;
/// - tout autre type, connu ou non : rien à ouvrir.
String? destinationNotification(
  NotificationClient n, {
  bool prestataire = false,
}) {
  final reservation = n.donnees['reservationId'];
  if (prestataire) {
    // Côté prestataire, seules les notifications de réservation ont un écran (le détail de la demande).
    return (n.type.startsWith('reservation_') ||
                n.type == 'rappel_rendez_vous') &&
            reservation != null
        ? Chemins.reservationPrestataire(reservation)
        : null;
  }
  final litige = n.donnees['litigeId'];
  if (n.type == 'reservation_terminee' && reservation != null) {
    return Chemins.avis(reservation);
  }
  if (n.type.startsWith('litige_') && litige != null) {
    return Chemins.signalement(litige);
  }
  if ((n.type.startsWith('reservation_') || n.type == 'rappel_rendez_vous') &&
      reservation != null) {
    return Chemins.detailReservation(reservation);
  }
  return null;
}

/// Pictogramme d'un type de notification.
IconData iconeNotification(String type) => switch (type) {
  'reservation_confirmee' => Icons.check_rounded,
  'reservation_acceptee' => Icons.event_available_outlined,
  'reservation_refusee' || 'reservation_annulee' => Icons.event_busy_outlined,
  'reservation_expiree' => Icons.schedule_rounded,
  'reservation_demarree' => Icons.play_arrow_rounded,
  'rappel_rendez_vous' => Icons.alarm_rounded,
  'reservation_terminee' ||
  'avis_recu' ||
  'avis_modere' => Icons.star_outline_rounded,
  'litige_ouvert' || 'litige_cloture' => Icons.flag_outlined,
  'sanction_posee' || 'sanction_levee' => Icons.gavel_rounded,
  'mot_de_passe_modifie' ||
  'reinitialisation_mot_de_passe' => Icons.lock_outline_rounded,
  _ => Icons.notifications_none_rounded,
};
