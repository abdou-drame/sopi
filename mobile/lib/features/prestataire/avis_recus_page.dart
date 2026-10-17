import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/routing/chemins.dart';
import '../reservation/widgets/liste_avis.dart';
import 'depot_prestataire.dart';

/// « Mes avis » (maquette PrestaAvis) : note moyenne, nombre d'avis et avis reçus, du plus récent au plus ancien.
class AvisRecusPage extends ConsumerWidget {
  const AvisRecusPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return ListeAvisPage(
      titreLigne1: 'Mes',
      titreLigne2: 'avis',
      chargerPage: (page) =>
          ref.read(depotPrestataireProvider).avisRecus(page: page),
      onRetour: () => context.canPop()
          ? context.pop()
          : context.go(Chemins.profilPrestataire),
    );
  }
}
