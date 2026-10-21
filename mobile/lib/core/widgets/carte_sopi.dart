import 'package:flutter/material.dart';

import '../theme/couleurs.dart';
import '../theme/dimensions.dart';

/// Carte des maquettes : blanc, rayon 26, ombre 0 1px 3px rgba(18,23,43,.06), aucune bordure.
class CarteSopi extends StatelessWidget {
  const CarteSopi({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(DimensionsSopi.margeCarte),
    this.rayon = DimensionsSopi.rayonCarte,
    this.onTap,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final double rayon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final forme = BorderRadius.circular(rayon);
    final contenu = Padding(padding: padding, child: child);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: CouleursSopi.blanc,
        borderRadius: forme,
        boxShadow: OmbresSopi.carte,
      ),
      child: onTap == null
          ? contenu
          : Material(
              type: MaterialType.transparency,
              child: InkWell(borderRadius: forme, onTap: onTap, child: contenu),
            ),
    );
  }
}
