import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/routing/chemins.dart';
import 'modeles.dart';

/// Point d'entrée de l'annulation depuis une carte ou le détail.
/// (Provisoire jusqu'à la feuille d'annulation : ouvre le détail de la réservation.)
Future<void> ouvrirAnnulation(
  BuildContext context,
  WidgetRef ref,
  ReservationClient reservation,
) async {
  context.push(Chemins.detailReservation(reservation.id));
}
