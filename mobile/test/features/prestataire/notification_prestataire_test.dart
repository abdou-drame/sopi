import 'package:flutter_test/flutter_test.dart';
import 'package:sopi/core/routing/chemins.dart';
import 'package:sopi/features/compte/modeles_notification.dart';

NotificationClient _notif(String type, Map<String, String> donnees) =>
    NotificationClient(
      id: 'n-1',
      type: type,
      titre: 'Titre',
      corps: 'Corps',
      dateCreation: DateTime.utc(2026, 10, 24),
      donnees: donnees,
    );

void main() {
  group('destinationNotification côté prestataire', () {
    test('une notification de réservation ouvre le détail de la demande', () {
      final n = _notif('reservation_demandee', {'reservationId': 'r-1'});
      expect(
        destinationNotification(n, prestataire: true),
        Chemins.reservationPrestataire('r-1'),
      );
    });

    test(
      'une réservation terminée n\'ouvre pas l\'écran d\'avis du client',
      () {
        final n = _notif('reservation_terminee', {'reservationId': 'r-1'});
        expect(
          destinationNotification(n, prestataire: true),
          Chemins.reservationPrestataire('r-1'),
        );
        expect(destinationNotification(n), Chemins.avis('r-1'));
      },
    );

    test('les autres types n\'ouvrent rien', () {
      final n = _notif('litige_ouvert', {'litigeId': 'l-1'});
      expect(destinationNotification(n, prestataire: true), isNull);
    });
  });
}
