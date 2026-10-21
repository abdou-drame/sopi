import 'package:flutter/material.dart';

import '../theme/couleurs.dart';

/// Logo Sopi des maquettes : carré bleu arrondi (rayon 18 pour 52) avec un « S » blanc en 800. Le nom « Sopi »
/// est toujours écrit à côté : le logo est exclu de la lecture d'écran.
class SopiLogo extends StatelessWidget {
  const SopiLogo({super.key, this.taille = 48, this.ombre = false});

  /// Côté du carré, en pixels logiques.
  final double taille;

  /// Ombre bleue portée (maquette Orientation) : 0 10px 22px rgba(29,78,216,.35).
  final bool ombre;

  @override
  Widget build(BuildContext context) {
    return ExcludeSemantics(
      child: Container(
        width: taille,
        height: taille,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: CouleursSopi.bleu,
          borderRadius: BorderRadius.circular(taille * 18 / 52),
          boxShadow: ombre
              ? const [
                  BoxShadow(
                    color: Color(0x591D4ED8),
                    blurRadius: 22,
                    offset: Offset(0, 10),
                  ),
                ]
              : null,
        ),
        child: Text(
          'S',
          style: TextStyle(
            color: CouleursSopi.blanc,
            fontSize: taille / 2,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}
