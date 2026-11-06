import 'package:flutter/material.dart';

import '../animation/apparition.dart';
import '../theme/couleurs.dart';

/// Onglets segmentés des maquettes : pilule #e9ecf3 de 48 px (rayon 24, marge 4), onglet actif en bleu
/// (rayon 20, texte blanc 700), onglets inactifs transparents (texte #6b7287, 600).
class OngletsSegmentes<T> extends StatelessWidget {
  const OngletsSegmentes({
    super.key,
    required this.valeurs,
    required this.libelle,
    required this.actif,
    required this.onChange,
    this.tailleTexte = 13,
    this.cle,
  });

  final List<T> valeurs;
  final String Function(T) libelle;
  final T actif;
  final ValueChanged<T> onChange;
  final double tailleTexte;

  /// Clé posée sur la zone touchable de chaque onglet (tests).
  final Key? Function(T)? cle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      height: 48,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: CouleursSopi.fondSecondaire,
        borderRadius: BorderRadius.circular(24),
      ),
      // La pastille bleue glisse sous l'onglet choisi ; les onglets restent au-dessus.
      child: Stack(
        children: [
          AnimatedAlign(
            duration: animationsActives(context)
                ? const Duration(milliseconds: 300)
                : Duration.zero,
            curve: Curves.easeOutCubic,
            alignment: Alignment(
              valeurs.length < 2
                  ? 0
                  : -1 + 2 * valeurs.indexOf(actif) / (valeurs.length - 1),
              0,
            ),
            child: FractionallySizedBox(
              widthFactor: 1 / valeurs.length,
              heightFactor: 1,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  color: CouleursSopi.bleu,
                  borderRadius: BorderRadius.circular(20),
                ),
              ),
            ),
          ),
          Row(
            children: [
              for (var i = 0; i < valeurs.length; i++) ...[
                if (i > 0) const SizedBox(width: 4),
                Expanded(
                  child: Semantics(
                    button: true,
                    selected: valeurs[i] == actif,
                    label: libelle(valeurs[i]),
                    excludeSemantics: true,
                    child: Material(
                      color: Colors.transparent,
                      borderRadius: BorderRadius.circular(20),
                      child: InkWell(
                        key: cle?.call(valeurs[i]),
                        borderRadius: BorderRadius.circular(20),
                        onTap: () => onChange(valeurs[i]),
                        child: Center(
                          child: AnimatedDefaultTextStyle(
                            duration: const Duration(milliseconds: 250),
                            style: theme.textTheme.labelLarge!.copyWith(
                              fontSize: tailleTexte,
                              fontWeight: valeurs[i] == actif
                                  ? FontWeight.w700
                                  : FontWeight.w600,
                              color: valeurs[i] == actif
                                  ? CouleursSopi.blanc
                                  : CouleursSopi.texteSecondaire,
                            ),
                            child: Text(libelle(valeurs[i]), maxLines: 1),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}
