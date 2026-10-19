import 'package:flutter/material.dart';

import '../theme/couleurs.dart';

/// Logo Sopi (« S » blanc et point orange sur fond bleu), de taille réglable. L'image est décorative :
/// le nom « Sopi » est toujours écrit à côté, elle est donc exclue de la lecture d'écran.
class SopiLogo extends StatelessWidget {
  const SopiLogo({super.key, this.taille = 48, this.ombre = false});

  /// Côté du carré, en pixels logiques.
  final double taille;

  /// Ombre bleue portée sous le logo (en-tête de l'orientation).
  final bool ombre;

  @override
  Widget build(BuildContext context) {
    // Même arrondi que sopi_icon_rounded (26 sur 120).
    final rayon = BorderRadius.circular(taille * 26 / 120);
    return ExcludeSemantics(
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: rayon,
          boxShadow: ombre
              ? [
                  BoxShadow(
                    color: CouleursSopi.bleu.withValues(alpha: 0.35),
                    blurRadius: 22,
                    offset: const Offset(0, 10),
                  ),
                ]
              : null,
        ),
        child: ClipRRect(
          borderRadius: rayon,
          child: Image.asset(
            'assets/logo/sopi_icon_1024.png',
            width: taille,
            height: taille,
            fit: BoxFit.cover,
            filterQuality: FilterQuality.medium,
          ),
        ),
      ),
    );
  }
}
