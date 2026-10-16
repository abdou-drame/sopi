import 'package:flutter/material.dart';

import '../theme/couleurs.dart';
import 'boutons.dart';

/// Liste ou écran sans contenu (« Aucune réservation pour le moment »).
class EtatVide extends StatelessWidget {
  const EtatVide({
    super.key,
    required this.message,
    this.titre,
    this.icone = Icons.inbox_outlined,
    this.libelleAction,
    this.onAction,
  });

  final String message;
  final String? titre;
  final IconData icone;
  final String? libelleAction;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircleAvatar(
              radius: 36,
              backgroundColor: CouleursSopi.bleuPastel,
              child: Icon(icone, color: CouleursSopi.bleu, size: 32),
            ),
            const SizedBox(height: 18),
            if (titre != null) ...[
              Text(
                titre!,
                style: theme.textTheme.titleMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 6),
            ],
            Text(
              message,
              style: theme.textTheme.bodyMedium,
              textAlign: TextAlign.center,
            ),
            if (libelleAction != null && onAction != null) ...[
              const SizedBox(height: 20),
              BoutonPrimaire(
                libelle: libelleAction!,
                onPressed: onAction,
                pleineLargeur: false,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Chargement plein écran ou dans une zone.
class EtatChargement extends StatelessWidget {
  const EtatChargement({super.key, this.message});

  final String? message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator(
            color: CouleursSopi.bleu,
            semanticsLabel: 'Chargement',
          ),
          if (message != null) ...[
            const SizedBox(height: 16),
            Text(message!, style: Theme.of(context).textTheme.bodyMedium),
          ],
        ],
      ),
    );
  }
}

/// Erreur de chargement avec bouton « Réessayer ».
class EtatErreur extends StatelessWidget {
  const EtatErreur({
    super.key,
    required this.message,
    required this.onReessayer,
  });

  final String message;
  final VoidCallback onReessayer;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircleAvatar(
              radius: 36,
              backgroundColor: CouleursSopi.erreurFond,
              child: Icon(
                Icons.wifi_off_rounded,
                color: CouleursSopi.erreurTexte,
                size: 32,
              ),
            ),
            const SizedBox(height: 18),
            Text(
              message,
              style: theme.textTheme.bodyLarge,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            BoutonPrimaire(
              libelle: 'Réessayer',
              onPressed: onReessayer,
              pleineLargeur: false,
            ),
          ],
        ),
      ),
    );
  }
}
