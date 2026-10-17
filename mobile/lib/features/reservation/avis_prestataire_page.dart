import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/routing/chemins.dart';
import 'depot_reservation.dart';
import 'widgets/liste_avis.dart';

/// « Avis sur {entreprise} » (maquette AvisPrestataire) : tous les avis d'un prestataire, vus par le client.
/// Le nom de l'entreprise est transmis par la fiche ; à défaut (lien direct), le titre reste générique.
class AvisPrestatairePage extends ConsumerWidget {
  const AvisPrestatairePage({
    super.key,
    required this.prestataireId,
    this.nomEntreprise,
  });

  final String prestataireId;
  final String? nomEntreprise;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final nom = nomEntreprise?.trim() ?? '';
    return ListeAvisPage(
      titreLigne1: 'Avis sur',
      titreLigne2: nom.isEmpty ? 'ce prestataire' : nom,
      chargerPage: (page) =>
          ref.read(depotReservationProvider).avis(prestataireId, page: page),
      onRetour: () => context.canPop()
          ? context.pop()
          : context.go(Chemins.fichePrestataire(prestataireId)),
    );
  }
}
