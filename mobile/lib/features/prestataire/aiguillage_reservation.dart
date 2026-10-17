import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../reservation/modeles.dart' show StatutReservation;
import 'depot_prestataire.dart';
import 'detail_demande_page.dart';
import 'prestation_page.dart';
import 'terminer_prestation_page.dart';

/// Écran d'une réservation du prestataire, choisi selon son statut relu à l'API :
/// confirmée → Prestation (saisie du code), en cours → Terminer la prestation. Les autres statuts gardent le détail de la demande.
class AiguillageReservationPrestataire extends ConsumerWidget {
  const AiguillageReservationPrestataire({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final statut = ref.watch(detailDemandeProvider(id)).value?.statut;
    return switch (statut) {
      StatutReservation.confirmee => PrestationPage(id: id),
      StatutReservation.enCours => TerminerPrestationPage(id: id),
      _ => DetailDemandePage(id: id),
    };
  }
}
