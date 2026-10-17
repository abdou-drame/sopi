import 'package:flutter/material.dart';

import '../theme/dimensions.dart';

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
