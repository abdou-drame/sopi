import 'package:flutter/material.dart';

import '../theme/couleurs.dart';

/// Interrupteur des maquettes : 48×28, rayon 14, bouton blanc de 22 px à 3 px du bord.
/// Allumé : bleu, bouton à droite. Éteint : gris, bouton à gauche.
class InterrupteurSopi extends StatelessWidget {
  const InterrupteurSopi({
    super.key,
    required this.valeur,
    required this.onChanged,
    required this.libelle,
  });

  final bool valeur;
  final ValueChanged<bool>? onChanged;

  /// Lu par les lecteurs d'écran.
  final String libelle;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      toggled: valeur,
      label: libelle,
      excludeSemantics: true,
      onTap: onChanged == null ? null : () => onChanged!(!valeur),
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onChanged == null ? null : () => onChanged!(!valeur),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          width: 48,
          height: 28,
          padding: const EdgeInsets.all(3),
          alignment: valeur ? Alignment.centerRight : Alignment.centerLeft,
          decoration: BoxDecoration(
            color: valeur ? CouleursSopi.bleu : CouleursSopi.bordureForte,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Container(
            width: 22,
            height: 22,
            decoration: const BoxDecoration(
              color: CouleursSopi.blanc,
              shape: BoxShape.circle,
            ),
          ),
        ),
      ),
    );
  }
}
