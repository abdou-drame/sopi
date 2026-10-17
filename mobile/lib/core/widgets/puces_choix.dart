import 'package:flutter/material.dart';

import '../theme/couleurs.dart';

/// Puces à choix unique (maquettes ServiceForm et PlageHoraire) : 38 px, rayon 19, texte 13.
/// La puce choisie est marine avec un texte blanc 700 ; les autres sont bordées, texte 600.
class PucesChoix<T> extends StatelessWidget {
  const PucesChoix({
    super.key,
    required this.valeurs,
    required this.libelle,
    required this.choisie,
    required this.onChoix,
    this.actif = true,
  });

  final List<T> valeurs;
  final String Function(T) libelle;
  final T? choisie;
  final ValueChanged<T> onChoix;
  final bool actif;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final valeur in valeurs)
          Semantics(
            button: true,
            selected: valeur == choisie,
            label: libelle(valeur),
            excludeSemantics: true,
            child: Material(
              color: valeur == choisie
                  ? CouleursSopi.marine
                  : Colors.transparent,
              shape: StadiumBorder(
                side: valeur == choisie
                    ? BorderSide.none
                    : const BorderSide(color: CouleursSopi.bordureForte),
              ),
              child: InkWell(
                customBorder: const StadiumBorder(),
                onTap: actif ? () => onChoix(valeur) : null,
                child: Container(
                  height: 38,
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  alignment: Alignment.center,
                  child: Text(
                    libelle(valeur),
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: valeur == choisie
                          ? FontWeight.w700
                          : FontWeight.w600,
                      color: valeur == choisie
                          ? CouleursSopi.blanc
                          : CouleursSopi.marine,
                    ),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}
