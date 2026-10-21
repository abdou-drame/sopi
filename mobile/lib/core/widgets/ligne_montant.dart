import 'package:flutter/material.dart';

import '../theme/couleurs.dart';

/// Ligne « libellé à gauche, valeur à droite » (récapitulatif d'un montant, d'une réservation…).
class LigneMontant extends StatelessWidget {
  const LigneMontant({
    super.key,
    required this.libelle,
    required this.valeur,
    this.fort = false,
    this.couleurValeur,
  });

  final String libelle;
  final String valeur;

  /// Mise en avant (total) : libellé et valeur en gras.
  final bool fort;
  final Color? couleurValeur;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(
              libelle,
              style: theme.textTheme.bodyMedium?.copyWith(
                fontWeight: fort ? FontWeight.w800 : FontWeight.w500,
                color: fort ? CouleursSopi.texte : CouleursSopi.texteSecondaire,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              valeur,
              textAlign: TextAlign.right,
              style: theme.textTheme.bodyLarge?.copyWith(
                fontWeight: fort ? FontWeight.w800 : FontWeight.w700,
                color: couleurValeur ?? CouleursSopi.texte,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
