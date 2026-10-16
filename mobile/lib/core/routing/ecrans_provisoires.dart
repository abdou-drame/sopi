import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/session.dart';
import '../theme/dimensions.dart';
import '../widgets/boutons.dart';

/// Écran PROVISOIRE : affiche seulement son nom. Remplacé écran par écran dans les prompts suivants.
class EcranProvisoire extends StatelessWidget {
  const EcranProvisoire({
    super.key,
    required this.nom,
    this.parametres = const {},
  });

  final String nom;

  /// Paramètres de la route (identifiants), affichés pour vérifier le routage.
  final Map<String, String> parametres;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(DimensionsSopi.margeEcran),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  nom,
                  style: theme.textTheme.headlineSmall,
                  textAlign: TextAlign.center,
                ),
                for (final e in parametres.entries)
                  Text(
                    '${e.key} : ${e.value}',
                    style: theme.textTheme.bodySmall,
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Onglet « Profil » provisoire : en plus de son nom, permet de tester la déconnexion.
class ProfilProvisoire extends ConsumerWidget {
  const ProfilProvisoire({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final etat = ref.watch(sessionProvider);
    final nom = etat is Connecte ? etat.utilisateur.nomComplet : '';
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(DimensionsSopi.margeEcran),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Profil',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                if (nom.isNotEmpty) Text(nom),
                const SizedBox(height: 24),
                BoutonSecondaire(
                  libelle: 'Se déconnecter',
                  onPressed: () =>
                      ref.read(sessionProvider.notifier).deconnecter(),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
